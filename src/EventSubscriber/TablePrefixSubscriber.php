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
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TablePrefixSubscriber implements EventSubscriberInterface
{
    private $prefix = 'phyxo_';

    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    public static function getSubscribedEvents()
    {
        return ['loadClassMetadata'];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event)
    {
        $classMetadata = $event->getClassMetadata();
        if (!$classMetadata->isInheritanceTypeSingleTable() || $classMetadata->getName() === $classMetadata->rootEntityName) {
            $classMetadata->setPrimaryTable([
                'name' => $this->prefix . $classMetadata->getTableName()
            ]);
        }

        if ($classMetadata->isIdGeneratorSequence()) {
            $newDefinition = $classMetadata->sequenceGeneratorDefinition;
            $newDefinition['sequenceName'] = $this->prefix . $newDefinition['sequenceName'];

            $classMetadata->setSequenceGeneratorDefinition($newDefinition);
            $em = $event->getEntityManager();
            if (isset($classMetadata->idGenerator)) {
                $sequenceGenerator = new \Doctrine\ORM\Id\SequenceGenerator(
                    $em->getConfiguration()->getQuoteStrategy()->getSequenceName(
                        $newDefinition,
                        $classMetadata,
                        $em->getConnection()->getDatabasePlatform()),
                    $newDefinition['allocationSize']
                );
                $classMetadata->setIdGenerator($sequenceGenerator);
            }
        }

        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if ($mapping['type'] == ClassMetadataInfo::MANY_TO_MANY && $mapping['isOwningSide']) {
                $mappedTableName = $mapping['joinTable']['name'];
                $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $this->prefix . $mappedTableName;
            }
        }
    }
}
