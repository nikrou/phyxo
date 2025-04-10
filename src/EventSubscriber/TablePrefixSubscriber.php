<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Id\SequenceGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TablePrefixSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly string $prefix)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoadClassMetadataEventArgs::class => 'loadClassMetadata'];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        /** @var ClassMetadata $classMetadata @phpstan-ignore-next-line */
        $classMetadata = $event->getClassMetadata();
        if (!$classMetadata->isInheritanceTypeSingleTable() || $classMetadata->getName() === $classMetadata->rootEntityName) {
            $classMetadata->setPrimaryTable([
                'name' => $this->prefix . $classMetadata->getTableName(),
            ]);
        }

        if ($classMetadata->isIdGeneratorSequence()) {
            $newDefinition = $classMetadata->sequenceGeneratorDefinition;
            $newDefinition['sequenceName'] = $this->prefix . $newDefinition['sequenceName'];

            $classMetadata->setSequenceGeneratorDefinition($newDefinition);
            $em = $event->getEntityManager();
            if ($classMetadata->idGenerator !== null) {
                $sequenceGenerator = new SequenceGenerator(
                    $em->getConfiguration()->getQuoteStrategy()->getSequenceName(
                        $newDefinition,
                        $classMetadata,
                        $em->getConnection()->getDatabasePlatform()
                    ),
                    (int) $newDefinition['allocationSize']
                );
                $classMetadata->setIdGenerator($sequenceGenerator);
            }
        }

        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if ($mapping['type'] == ClassMetadata::MANY_TO_MANY && $mapping['isOwningSide']) {
                $mappedTableName = $mapping['joinTable']['name'];
                $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $this->prefix . $mappedTableName;
            }
        }
    }
}
