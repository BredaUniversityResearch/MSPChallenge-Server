<?php

namespace App\Controller;

use App\Domain\Helper\Util;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\TableDiff;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ToolsController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('tools/index.html.twig');
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function checkMissingIndices(Request $request, Connection $connection): Response
    {
        $form = $this->createFormBuilder()
            ->add('regexp', TextareaType::class, [
                'required' => false,
                'data' => '/\S+_id/'
            ])
            ->add('regexp_exclude', TextareaType::class, [
                'required' => false,
            ])
            ->add('type_prefix', TextareaType::class, [
                'required' => false,
            ])
            ->add('regexp_tables', TextareaType::class, [
                'required' => false,
            ])
            ->add('skip_preview', CheckboxType::class, [
                'required' => false,
            ])
            ->add('go', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);
        $messages = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->processCheckMissingIndicesFormData(
                $connection,
                $data,
                $messages
            );
        }

        return $this->render('tools/check_missing_indices.html.twig', [
            'form' => $form->createView(),
            'messages' => $messages
        ]);
    }

    private function processCheckMissingIndicesFormData(Connection $connection, array $data, array &$messages)
    {
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
                if (!empty($data['regexp']) &&
                    preg_match($data['regexp'], $column->getName(), $pregMatches) !== 1) {
                    continue;
                }
                if (!empty($pregMatches) &&
                    $pregMatches[0] !== $column->getName()) {
                    continue;
                }
                if (!empty($data['type_prefix']) &&
                    !Util::hasPrefix(
                        $column->getType()->getName(),
                        array_map('trim', explode(',', $data['type_prefix']))
                    )
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

        $messages = [];
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
                $messages[] = '<span style="color: red;">Missing index for table "' . $tableName . '" and field ' .
                    $column->getName() . '</span>...';
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
