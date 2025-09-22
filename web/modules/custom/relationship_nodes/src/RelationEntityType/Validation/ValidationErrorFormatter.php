<?php

namespace Drupal\relationship_nodes\RelationEntityType\Validation;

use Drupal\Core\StringTranslation\StringTranslationTrait;

class ValidationErrorFormatter {

    use StringTranslationTrait;


    private const ERROR_MESSAGES = [
        'no_field_storage' => 'The field "@field" has no valid field storage.',
        'invalid_field_type' => 'Field "@field" has an invalid field type.',
        'invalid_cardinality' => 'Field "@field" has an invalid cardinality.',
        'invalid_target_type' => 'Field "@field" has an invalid target type.',
        'invalid_mirror_type' => 'Vocabulary "@bundle" has no or an invalid mirror type. Only the options none, entity_reference and string are valid.',
        'invalid_entity_type' => 'Invalid entity type for relation configuration: only node_type and taxonomy_vocabulary are allowed.',
        'missing_field_config' => 'Field "@field" exists, but its field configuration is not found.',
        'multiple_target_bundles' => 'Field "@field" in bundle "@bundle" can only target one bundle.',
        'field_cannot_be_required' => 'Field "@field" in bundle "@bundle" cannot be required due to IEF widget conflicts.',
        'missing_config_file_data' => 'Field "@field" has a configuration file, but its content cannot be found.',
        'missing_field_name_config' => 'The relationship node module lacks the required field name configuration. (Cf. config/install.)',
        'disabled_with_dependencies' => 'Vocabulary "@bundle" is used as a relation type in a relationship node. Remove this dependency before disabling relationship nodes.',     
        'orphaned_rn_field_settings' => 'Field "@field" has relation settings but is not in module configuration.',
        'invalid_relation_vocabulary' => 'Field "@field" in bundle "@bundle" targets an invalid relation vocabulary.',
        'mirror_field_bundle_mismatch' => 'Mirror field "@field" in bundle "@bundle" must reference the same vocabulary.',
        'relation_type_field_no_targets' => 'Relation type field "@field" in bundle "@bundle" must have target bundles.',        
    ];


    public function formatValidationErrors(string $name, array $errors): string {
        $message = "Validation errors for {$name}:\n";
        $error_strings = [];
        foreach ($errors as $error) {
            if (isset($error['error_code']) && isset($error['context'])) {
                $error_string = "- " . $this->errorCodeToMessage($error['error_code'], $error['context']) . "\n";   
                if(in_array($error_string, $error_strings)){
                    continue;
                }
                $error_strings[] = $error_string;
                $message .= $error_string;
            }   
        }  
        return rtrim($message, "\n");
    }
    
    
    protected function errorCodeToMessage(string $error_code, array $context): string{
        $error_message = self::ERROR_MESSAGES[$error_code] ?? $error_code;
        return $this->t($error_message, $context);
    }  
}