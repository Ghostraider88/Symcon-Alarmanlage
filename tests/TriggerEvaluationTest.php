<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../libs/AlarmConstants.php';
require_once __DIR__ . '/../libs/SensorTrait.php';

/**
 * Lightweight host for the pure trigger evaluation logic.
 */
class TriggerEvaluationHost
{
    use SensorTrait;
}

/**
 * Unit tests for the pure, Symcon-independent trigger evaluation.
 */
class TriggerEvaluationTest extends TestCase
{
    public function testBoolTrue(): void
    {
        $this->assertTrue(TriggerEvaluationHost::EvaluateTrigger(true, AlarmConstants::TRIGGER_BOOL_TRUE, 0));
        $this->assertFalse(TriggerEvaluationHost::EvaluateTrigger(false, AlarmConstants::TRIGGER_BOOL_TRUE, 0));
    }

    public function testBoolFalse(): void
    {
        $this->assertTrue(TriggerEvaluationHost::EvaluateTrigger(false, AlarmConstants::TRIGGER_BOOL_FALSE, 0));
        $this->assertFalse(TriggerEvaluationHost::EvaluateTrigger(true, AlarmConstants::TRIGGER_BOOL_FALSE, 0));
    }

    public function testIntEquals(): void
    {
        $this->assertTrue(TriggerEvaluationHost::EvaluateTrigger(2, AlarmConstants::TRIGGER_INT_EQUALS, 2));
        $this->assertFalse(TriggerEvaluationHost::EvaluateTrigger(3, AlarmConstants::TRIGGER_INT_EQUALS, 2));
    }

    public function testIntNotEquals(): void
    {
        $this->assertTrue(TriggerEvaluationHost::EvaluateTrigger(3, AlarmConstants::TRIGGER_INT_NOT_EQUALS, 2));
        $this->assertFalse(TriggerEvaluationHost::EvaluateTrigger(2, AlarmConstants::TRIGGER_INT_NOT_EQUALS, 2));
    }

    public function testIntGreaterThan(): void
    {
        $this->assertTrue(TriggerEvaluationHost::EvaluateTrigger(5, AlarmConstants::TRIGGER_INT_GREATER_THAN, 0));
        $this->assertFalse(TriggerEvaluationHost::EvaluateTrigger(0, AlarmConstants::TRIGGER_INT_GREATER_THAN, 0));
    }

    public function testIntGreaterOrEqual(): void
    {
        $this->assertTrue(TriggerEvaluationHost::EvaluateTrigger(5, AlarmConstants::TRIGGER_INT_GREATER_EQUAL, 5));
        $this->assertFalse(TriggerEvaluationHost::EvaluateTrigger(4, AlarmConstants::TRIGGER_INT_GREATER_EQUAL, 5));
    }

    public function testIntLessThan(): void
    {
        $this->assertTrue(TriggerEvaluationHost::EvaluateTrigger(3, AlarmConstants::TRIGGER_INT_LESS_THAN, 5));
        $this->assertFalse(TriggerEvaluationHost::EvaluateTrigger(5, AlarmConstants::TRIGGER_INT_LESS_THAN, 5));
    }

    public function testIntLessOrEqual(): void
    {
        $this->assertTrue(TriggerEvaluationHost::EvaluateTrigger(5, AlarmConstants::TRIGGER_INT_LESS_EQUAL, 5));
        $this->assertFalse(TriggerEvaluationHost::EvaluateTrigger(6, AlarmConstants::TRIGGER_INT_LESS_EQUAL, 5));
    }

    public function testUnknownTriggerNeverFires(): void
    {
        $this->assertFalse(TriggerEvaluationHost::EvaluateTrigger(true, 999, 0));
    }
}
