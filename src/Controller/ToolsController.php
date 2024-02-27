<?php

namespace App\Controller;

use App\Domain\Services\ConnectionManager;
use App\Domain\Services\SymfonyToLegacyHelper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ToolsController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('tools/index.html.twig');
    }

    public function apcu(string $projectDir): Response
    {
        $apcRelativePath = 'var/tools/apc.php';
        $apcFilePath = $projectDir . '/' . $apcRelativePath;
        // Check if the file exists
        if (!file_exists($apcFilePath)) {
            throw new \RuntimeException($apcRelativePath . ' not found. Please call: bash install-tools.sh');
        }


        // Include the apc.php file
        ob_start();
        // setup apc.php
        define('USE_AUTHENTICATION', 0);
        // make global variables required by apc.php available
        global $time;
        global $col_black;
        global $MYREQUEST, $MY_SELF_WO_SORT;
        global $MYREQUEST,$MY_SELF;
        global $MY_SELF,$MYREQUEST,$AUTHENTICATED;
        $_SERVER['PHP_SELF'] = $this->generateUrl('_tools_apcu');
        // Temporarily suppress warnings
        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_WARNING);
        require $apcFilePath;
        // Restore previous error reporting level
        error_reporting($previousErrorReporting);
        $content = ob_get_clean();

        // Return the response
        return new Response($content);
    }

    /**
     * @throws DBALException
     */
    public function checkMissingIndices(
        Request $request,
        ConnectionManager $connectionManager,
        // below is required by legacy to be auto-wire, has its own ::getInstance()
        SymfonyToLegacyHelper $helper
    ): Response {
        $form = $this->createFormBuilder()
            ->add('regexp_database', TextType::class, [
                'required' => false,
                'label' => 'Enter regexp to match database or leave empty: ',
                'help' => 'Leave empty to traverse all dbs',
                'data' => '/msp_session_\d+/'
            ])
            ->add('regexp_type', TextareaType::class, [
                'required' => false,
                'label' => 'Enter regexp to match field type or leave empty: ',
                'help' => 'Leave empty to traverse all types, or use parentheses and pipelines to add multiple ' .
                    'regexp, e.g. /(integer|string|text|bigint|datetime|boolean|float)/'
            ])
            ->add('regexp', TextareaType::class, [
                'required' => false,
                'data' => '/\S+_id/',
                'label' => 'Enter regexp to match field or leave empty: ',
                'help' => 'Default is /\S+_id/, which means one of more non-whitespace characters followed by _id'
            ])
            ->add('regexp_exclude', TextareaType::class, [
                'required' => false,
                'label' => 'Enter regexp to match field to exclude or leave empty to not exclude anything: '
            ])
            ->add('regexp_tables', TextareaType::class, [
                'required' => false,
                'label' => 'Enter tables prefix to match tables or leave empty: ',
                'help' => 'Leave empty to traverse all tables'
            ])
            ->add('skip_preview', CheckboxType::class, [
                'required' => false,
                'help' => 'Skipping preview will automatically create an index of type INDEX where it is missing'
            ])
            ->add('go', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);
        $messages = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->processCheckMissingIndicesFormData(
                $connectionManager,
                $data,
                $messages
            );
        }

        return $this->render('tools/check_missing_indices.html.twig', [
            'form' => $form->createView(),
            'messages' => $messages
        ]);
    }

    /**
     * @throws DBALException
     */
    private function processCheckMissingIndicesFormData(
        ConnectionManager $connectionManager,
        array $data,
        array &$messages
    ) {
        $dbNames = $connectionManager->getDbNames();
        foreach ($dbNames as $dbName) {
            if (!empty($data['regexp_database']) &&
                preg_match($data['regexp_database'], $dbName, $pregMatches) !== 1) {
                continue;
            }

            $messages[] = '<h3>Database ' . $dbName . '</h3>';
            $this->processCheckMissingIndicesFormDataForSelectedDatabase(
                $connectionManager->getCachedDbConnection($dbName),
                $data,
                $messages
            );
        }
    }

    /**
     * @throws DBALException
     */
    private function processCheckMissingIndicesFormDataForSelectedDatabase(
        Connection $connection,
        array $data,
        array &$messages
    ) {
        $sm = $connection->createSchemaManager();
        $tables = $sm->listTables();
        $tablesToTraverse = [];
        foreach ($tables as $table) {
            if (!empty($data['regexp_tables']) &&
                preg_match($data['regexp_tables'], $table->getName(), $pregMatches) !== 1) {
                continue;
            }
            $tablesToTraverse[] = $table;
        }

        $matchedColumns = [];
        foreach ($tablesToTraverse as $table) {
            $columns = $table->getColumns();
            foreach ($columns as $column) {
                if (!empty($data['regexp_exclude']) &&
                    preg_match(
                        $data['regexp_exclude'],
                        $column->getName(),
                        $pregMatches
                    ) === 1 && $pregMatches[0] === $column->getName()) {
                    continue;
                }
                unset($pregMatches);
                if (!empty($data['regexp']) &&
                    preg_match($data['regexp'], $column->getName(), $pregMatches) !== 1
                ) {
                    continue;
                }
                if (!empty($pregMatches) && $pregMatches[0] !== $column->getName()) {
                    continue;
                }
                unset($pregMatches);
                if (!empty($data['regexp_type']) &&
                    preg_match($data['regexp_type'], $column->getType()->getName(), $pregMatches) !== 1
                ) {
                    continue;
                }
                $matchedColumns[$table->getName()][$column->getName()] = $column;
            }
        }

        $keys = array();
        $keyToMatchedFields = array();
        $columnToKeysMap = array();
        foreach ($tablesToTraverse as $table) {
            $tableName = $table->getName();
            $indexes = $table->getIndexes();
            if (empty($indexes)) {
                continue;
            }
            foreach ($indexes as $index) {
                foreach ($index->getColumns() as $seqInIndex => $columnName) {
                    $keys[$tableName][$index->getName()][$seqInIndex] = $columnName;
                    $columnToKeysMap[$tableName][$columnName][] = $index->getName();

                    if (!isset($matchedColumns[$tableName]) ||
                        !isset($matchedColumns[$tableName][$columnName])) {
                        continue;
                    }

                    $keyToMatchedFields[$tableName][$index->getName()][$seqInIndex] = $columnName;
                }
            }
        }

        $missingIndices = array();
        foreach ($tablesToTraverse as $table) {
            $tableName = $table->getName();
            if (isset($keyToMatchedFields[$tableName])) {
                $keyToMatchedFieldsPerTable = $keyToMatchedFields[$tableName];
                foreach ($keyToMatchedFieldsPerTable as $keyName => $fieldNames) {
                    $messages[] = 'Found table ' . $tableName . ' with index "' . $keyName . '" matching fields: ' .
                        implode(', ', $fieldNames) . '. Index fields: ' .
                        implode(', ', $keys[$tableName][$keyName]) . '...';
                }
            }
            $columns = $table->getColumns();
            foreach ($columns as $column) {
                if (!isset($matchedColumns[$tableName]) ||
                    !isset($matchedColumns[$tableName][$column->getName()])) {
                    continue;
                }
                if (isset($columnToKeysMap[$tableName][$column->getName()])) {
                    $arrFieldKeys = $columnToKeysMap[$tableName][$column->getName()];
                    foreach ($arrFieldKeys as $keyName) {
                        if (count($keys[$tableName][$keyName]) === 1) {
                            // Field is the only field in the index, so it is properly indexed.
                            continue 2;
                        }
                    }
                }

                $missingIndices[$tableName][$column->getName()] = $column->getName();
                $messages[] = '<span style="color: red;">Missing index for table ' . $tableName . ' and field ' .
                    $column->getName() . ' of type ' . $column->getType()->getName() . '</span>...';
            }
        }

        if ($data['skip_preview'] !== true || empty($missingIndices)) {
            return;
        }

        $messages[] = 'Creating missing indices...';
        foreach ($missingIndices as $tableName => $fieldNames) {
            foreach ($fieldNames as $fieldName) {
                $tableDiff = new TableDiff($tableName);
                $tableDiff->addedIndexes[] = new Index($fieldName, [$fieldName]);
                try {
                    $sm->alterTable($tableDiff);
                } catch (\Exception $exception) {
                    $messages[] = '...<span style="color: red;">Error: ' . $exception->getMessage() . '</span>';
                    continue;
                }
                $messages[] = '<span style="color: green">Added index: ' . $fieldName . '</span>';
            }
        }
        $messages[] = '<strong style="color: green">Done</strong>';
    }
}
