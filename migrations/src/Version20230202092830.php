<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\MSPDatabaseType;
use App\Migration\MSPMigration;
use App\Domain\API\v1\GeneralPolicyType;
use App\Domain\API\v1\Plan;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

final class Version20230202092830 extends MSPMigration
{
    public function getDescription(): string
    {
        return 'convert plan_type column of table plan from varchar to int';
    }

    protected function getDatabaseType(): MSPDatabaseType
    {
        return new MSPDatabaseType(MSPDatabaseType::DATABASE_TYPE_GAME_SESSION);
    }

    /**
     * @throws Exception
     */
    protected function onUp(Schema $schema): void
    {
        if (!$this->checkPreRequisites($schema, Types::STRING)) {
            $this->write("Column plan_type for table plan is not a string, already migrated?");
            return;
        }
        $qb = $this->getReadConnection()->createQueryBuilder();
        $result = $qb
            ->select('plan_id, plan_type')
            ->from('plan')
            ->executeQuery();
        // eg. [1 => '1,0,0', 2 =? '1,0,0', 3 => '0,0,0', 4 => '0,1,0', 5 => '1,0,0']
        $planTypes = collect($result->fetchAllAssociative() ?: [])->keyBy('plan_id')->map(fn($p) => $p['plan_type'])
            ->all();
        // convert column to int, all values be 0 for now
        $this->addSql(
            "ALTER TABLE `plan` CHANGE `plan_type` `plan_type` int NOT NULL DEFAULT '0' ".
            "COMMENT 'If a plan is energy/fishing/shipping. bit flags' AFTER `plan_constructionstart`"
        );
        // now set the right bit flags based on the old string values
        foreach ($planTypes as $planId => $planType) {
            $this->addSql(
                'UPDATE plan SET plan_type=? WHERE plan_id=?',
                [Plan::convertToNewPlanType($planType), $planId]
            );
        }
    }

    /**
     * @throws SchemaException
     * @throws Exception
     */
    protected function onDown(Schema $schema): void
    {
        if (!$this->checkPreRequisites($schema, Types::INTEGER)) {
            $this->write("Column plan_type for table plan is not a integer, already down-grated?");
            return;
        }
        $qb = $this->getReadConnection()->createQueryBuilder();
        $result = $qb
            ->select('plan_id, plan_type')
            ->from('plan')
            ->executeQuery();
        // eg. [1 => '1,0,0', 2 =? '1,0,0', 3 => '0,0,0', 4 => '0,1,0', 5 => '1,0,0']
        $planTypes = collect($result->fetchAllAssociative() ?: [])->keyBy('plan_id')->map(fn($p) => $p['plan_type'])
            ->all();
        // convert column to string, all values be empty string for now
        $this->addSql(
            "ALTER TABLE `plan` CHANGE `plan_type` `plan_type` VARCHAR(75) NOT NULL ".
            "COMMENT 'If a plan is energy/ecology/shipping. Comma separated value' AFTER `plan_constructionstart`"
        );
        // now set the right string based on the int values
        foreach ($planTypes as $planId => $planType) {
            $oldPLanType = (($planType & GeneralPolicyType::ENERGY) === GeneralPolicyType::ENERGY) ? '1' : '0';
            $oldPLanType .= ','.((($planType & GeneralPolicyType::FISHING) === GeneralPolicyType::FISHING) ? '1' : '0');
            $oldPLanType .= ','.
                ((($planType & GeneralPolicyType::SHIPPING) === GeneralPolicyType::SHIPPING) ? '1' : '0');
            $this->addSql('UPDATE plan SET plan_type=? WHERE plan_id=?', [$oldPLanType, $planId]);
        }
    }

    /**
     * @throws SchemaException
     * @throws Exception
     */
    private function checkPreRequisites(Schema $schema, string $typeName): bool
    {
        if (!$schema->hasTable('plan')) {
            return false;
        }
        if (!$schema->getTable('plan')->hasColumn('plan_type')) {
            return false;
        }
        if (Type::lookupName($schema->getTable('plan')->getColumn('plan_type')->getType()) != $typeName) {
            return false;
        }
        return true;
    }
}
