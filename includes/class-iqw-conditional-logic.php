<?php
/**
 * Conditional Logic Engine
 * Evaluates show/hide conditions for fields and steps.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class IQW_Conditional_Logic {

    /**
     * Evaluate a set of conditions against submitted data
     *
     * @param array $conditions Condition rules
     * @param array $data       Submitted form data
     * @return bool Whether conditions are met
     */
    public function evaluate_conditions( $conditions, $data ) {
        if ( empty( $conditions ) || empty( $conditions['rules'] ) ) {
            return true;
        }

        $logic = $conditions['logic'] ?? 'and'; // 'and' or 'or'
        $rules = $conditions['rules'];

        $results = array();
        foreach ( $rules as $rule ) {
            $results[] = $this->evaluate_rule( $rule, $data );
        }

        if ( $logic === 'or' ) {
            return in_array( true, $results, true );
        }

        // Default: AND
        return ! in_array( false, $results, true );
    }

    /**
     * Evaluate a single rule
     */
    private function evaluate_rule( $rule, $data ) {
        $field    = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? 'is';
        $value    = $rule['value'] ?? '';

        $field_value = $data[ $field ] ?? '';

        // Handle array values (checkbox groups)
        if ( is_array( $field_value ) ) {
            return $this->evaluate_array_rule( $field_value, $operator, $value );
        }

        switch ( $operator ) {
            case 'is':
            case 'equals':
                return strtolower( (string) $field_value ) === strtolower( (string) $value );

            case 'is_not':
            case 'not_equals':
                return strtolower( (string) $field_value ) !== strtolower( (string) $value );

            case 'contains':
                return stripos( (string) $field_value, (string) $value ) !== false;

            case 'not_contains':
                return stripos( (string) $field_value, (string) $value ) === false;

            case 'starts_with':
                return stripos( (string) $field_value, (string) $value ) === 0;

            case 'ends_with':
                return substr( strtolower( (string) $field_value ), -strlen( $value ) ) === strtolower( (string) $value );

            case 'empty':
                return trim( (string) $field_value ) === '';

            case 'not_empty':
                return trim( (string) $field_value ) !== '';

            case 'gt':
            case 'greater_than':
                return floatval( $field_value ) > floatval( $value );

            case 'lt':
            case 'less_than':
                return floatval( $field_value ) < floatval( $value );

            case 'gte':
                return floatval( $field_value ) >= floatval( $value );

            case 'lte':
                return floatval( $field_value ) <= floatval( $value );

            case 'in':
                $values = is_array( $value ) ? $value : explode( ',', $value );
                $values = array_map( 'trim', $values );
                $values = array_map( 'strtolower', $values );
                return in_array( strtolower( (string) $field_value ), $values, true );

            case 'not_in':
                $values = is_array( $value ) ? $value : explode( ',', $value );
                $values = array_map( 'trim', $values );
                $values = array_map( 'strtolower', $values );
                return ! in_array( strtolower( (string) $field_value ), $values, true );

            default:
                return true;
        }
    }

    /**
     * Evaluate rule for array values (checkbox groups, multi-select)
     */
    private function evaluate_array_rule( $field_value, $operator, $value ) {
        switch ( $operator ) {
            case 'contains':
                return in_array( $value, $field_value, true );
            case 'not_contains':
                return ! in_array( $value, $field_value, true );
            case 'empty':
                return empty( $field_value );
            case 'not_empty':
                return ! empty( $field_value );
            default:
                return true;
        }
    }

    /**
     * Get visible steps based on current data
     * Used to determine which steps to show in the wizard
     */
    public function get_visible_steps( $config, $data ) {
        if ( empty( $config['steps'] ) ) return array();

        $visible = array();
        foreach ( $config['steps'] as $index => $step ) {
            if ( empty( $step['conditions'] ) || $this->evaluate_conditions( $step['conditions'], $data ) ) {
                $visible[] = $index;
            }
        }

        return $visible;
    }

    /**
     * Get visible fields within a step
     */
    public function get_visible_fields( $step, $data ) {
        if ( empty( $step['fields'] ) ) return array();

        $visible = array();
        foreach ( $step['fields'] as $field ) {
            if ( empty( $field['conditions'] ) || $this->evaluate_conditions( $field['conditions'], $data ) ) {
                $visible[] = $field;
            }
        }

        return $visible;
    }
}
