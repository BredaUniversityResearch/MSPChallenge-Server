<?php

namespace App\Tests;

use App\Entity\SessionAPI\ImmersiveSession;
use App\Tests\Utils\ResourceHelper;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineExtensionsEntityTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

   /**
    * @description after an insert (without specifying created_at):
    * - retrieving the entity should have a created_at set to a timestamp as well as updated_at
    * - deleted_at should be NULL.
    */
    public function testTimestampableOnInsert(): void
    {
        $entity = new ImmersiveSession();
        $this->assertTrue(in_array(TimestampableEntity::class, class_uses($entity)));
        $this->assertTrue(in_array(SoftDeleteableEntity::class, class_uses($entity)));
        $entity
            ->setName('Test Session')
            ->setBottomLeftX(3957954)
            ->setBottomLeftY(3320200)
            ->setTopRightX(4108655)
            ->setTopRightY(3459703);
        $this->em->persist($entity);
        $this->em->flush();
        $this->em->refresh($entity);

        $this->assertNotNull($entity->getCreatedAt());
        $this->assertNotNull($entity->getUpdatedAt());
        $this->assertNull($entity->getDeletedAt());
    }

   /**
    * @description after an update:
    * - the updated_at should have been updated (different then before).
    * - The created_at should not be changed.
    * - deleted_at should be NULL.
    */
    public function testTimestampableOnUpdate(): void
    {
        $entity = new ImmersiveSession();
        $this->assertTrue(in_array(TimestampableEntity::class, class_uses($entity)));
        $this->assertTrue(in_array(SoftDeleteableEntity::class, class_uses($entity)));
        $entity
            ->setName('Test Session')
            ->setBottomLeftX(3957954)
            ->setBottomLeftY(3320200)
            ->setTopRightX(4108655)
            ->setTopRightY(3459703);
        $this->em->persist($entity);
        $this->em->flush();
        $this->em->refresh($entity);

        $createdAt = $entity->getCreatedAt();
        $updatedAtBefore = $entity->getUpdatedAt();

        // Simulate update (set a field)
        sleep(2);
        $entity
            ->setName('Test Session - updated');
        $this->em->flush();
        $this->em->refresh($entity);

        $this->assertSame($createdAt->getTimestamp(), $entity->getCreatedAt()->getTimestamp());
        $this->assertNotEquals($updatedAtBefore->getTimestamp(), $entity->getUpdatedAt()->getTimestamp());
        $this->assertNull($entity->getDeletedAt());
    }

    /**
     * @description after soft delete (removal):
     * - deleted_at should be set to a timestamp (not NULL).
     * - The softdelete filter must be disabled to verify this.
     */
    public function testSoftDeleteableOnRemove(): void
    {
        $entity = new ImmersiveSession();
        $entity
            ->setName('Test Session')
            ->setBottomLeftX(3957954)
            ->setBottomLeftY(3320200)
            ->setTopRightX(4108655)
            ->setTopRightY(3459703);
        $this->em->persist($entity);
        $this->em->flush();
        $id = $entity->getId();

        // Disable SoftDeleteable filter
        $this->em->getFilters()->disable('softdeleteable');

        $this->em->remove($entity);
        $this->em->flush();

        $this->em->clear();
        $deletedEntity = $this->em->getRepository(ImmersiveSession::class)->find($id);

        $this->assertNotNull($deletedEntity->getDeletedAt());
    }

    /**
     * @description after soft delete (removal):
     * - enable the softdelete filter
     * - retrieve rows
     * - and verify the entity (with previous id) is not listed anymore.
     */
    public function testSoftDeleteableFilterEnabled(): void
    {
        $entity = new ImmersiveSession();
        $entity
            ->setName('Test Session')
            ->setBottomLeftX(3957954)
            ->setBottomLeftY(3320200)
            ->setTopRightX(4108655)
            ->setTopRightY(3459703);
        $this->em->persist($entity);
        $this->em->flush();
        $id = $entity->getId();

        // Remove entity (soft delete)
        $this->em->remove($entity);
        $this->em->flush();

        // Enable SoftDeleteable filter
        $this->em->getFilters()->enable('softdeleteable');

        $this->em->clear();
        $deletedEntity = $this->em->getRepository(ImmersiveSession::class)->find($id);

        $this->assertNull($deletedEntity);
    }

    public static function setUpBeforeClass(): void
    {
        ResourceHelper::resetDatabases(
            static::bootKernel()->getProjectDir(),
            [ResourceHelper::OPTION_GAME_SESSION_COUNT => 1]
        );
    }
}
