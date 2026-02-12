<?php

namespace Drupal\relationship_nodes\Validation;

/**
 * Immutable validation result.
 */
final class ValidationResult {

  private function __construct(
    private readonly bool $valid,
    private readonly array $errors,
  ) {}

  public static function valid(): self {
    return new self(true, []);
  }

  public static function invalid(array $errors): self {
    return new self(false, $errors);
  }

  public function isValid(): bool {
    return $this->valid;
  }

  public function getErrors(): array {
    return $this->errors;
  }

  public function hasErrors(): bool {
    return !empty($this->errors);
  }

  /**
   * Merge multiple validation results.
   */
  public function merge(ValidationResult $other): self {
    if ($this->valid && $other->valid) {
      return self::valid();
    }
    
    return self::invalid(
      array_merge($this->errors, $other->errors)
    );
  }

  /**
   * Get formatted error messages.
   */
  public function getFormattedErrors(ValidationResultFormatter $formatter, string $name): string {
    if ($this->valid) {
      return '';
    }
    
    return $formatter->formatValidationErrors($name, $this->errors);
  }
}