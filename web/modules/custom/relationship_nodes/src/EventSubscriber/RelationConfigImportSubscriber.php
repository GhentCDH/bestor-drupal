<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvents;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;

class RelationConfigImportSubscriber implements EventSubscriberInterface {

    protected RelationBundleSettingsManager $settingsManager;
    protected RelationFieldConfigurator $fieldConfigurator;

    public function __construct(RelationBundleSettingsManager $settingsManager, RelationFieldConfigurator $fieldConfigurator,) {
        $this->settingsManager = $settingsManager;
        $this->fieldConfigurator = $fieldConfigurator;
    }

    public static function getSubscribedEvents(): array {
        // Dit event wordt getriggerd voor een config import.
        return [
            ConfigEvents::IMPORT => 'onConfigImport',
        ];
    }

    /**
     * Controleer node types en vocabularies bij config import.
     */
    public function onConfigImport(ConfigImporterEvent $event): void {
      
      /*  $storage = $event->getConfigStorage();

        // Loop over alle node types
        foreach ($storage->listAll() as $config_name) {
            if (str_starts_with($config_name, 'node.type.')) {
                $node_type_id = substr($config_name, strlen('node.type.'));
                $node_type = $this->settingsManager->ensureNodeType($node_type_id);
                if ($node_type && $this->settingsManager->isRelationNodeType($node_type)) {
                    $status = $this->fieldConfigurator->getFieldStatus($node_type); // wijzigen
                    if (!empty($status['missing']) || !empty($status['remove'])) {
                        throw new \Exception("Node type {$node_type_id} heeft ontbrekende of incorrecte relation fields bij config import.");
                    }
                }
            }

            // Loop over vocabularies
            if (str_starts_with($config_name, 'taxonomy.vocabulary.')) {
                $vocab_id = substr($config_name, strlen('taxonomy.vocabulary.'));
                $vocab = $this->settingsManager->ensureVocab($vocab_id);
                if ($vocab && $this->settingsManager->isRelationVocab($vocab)) {
                    $status = $this->fieldConfigurator->getFieldStatus($vocab); //wijzigen
                    if (!empty($status['missing']) || !empty($status['remove'])) {
                        throw new \Exception("Vocabulary {$vocab_id} heeft ontbrekende of incorrecte relation fields bij config import.");
                    }
                }
            }
        }*/
    }
}
