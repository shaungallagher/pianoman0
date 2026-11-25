<?php
declare(strict_types=1);

final class PianoModel
{
    private const NOTE_COUNT = 88;
    private const CONTEXT_LENGTH = 4;
    private const FEATURE_COUNT = self::NOTE_COUNT * self::CONTEXT_LENGTH;
    private const LEARNING_RATE = 0.18;
    private const MAX_WEIGHT = 100000.0;

    public static function create(): array
    {
        $weights = [];
        for ($note = 0; $note < self::NOTE_COUNT; $note++) {
            $weights[] = array_fill(0, self::FEATURE_COUNT + 1, 0.0);
        }

        return [
            'version' => 3,
            'weights' => $weights,
            'examples' => 0,
            'history' => [],
        ];
    }

    public static function isValid(array $model): bool
    {
        if (!self::isInteger($model['version'] ?? null) ||
            ($model['version'] !== 2 && $model['version'] !== 2.0 &&
                $model['version'] !== 3 && $model['version'] !== 3.0) ||
            !isset($model['weights']) || !is_array($model['weights']) ||
            count($model['weights']) !== self::NOTE_COUNT ||
            !self::isList($model['weights']) ||
            !isset($model['history']) || !is_array($model['history']) ||
            count($model['history']) > 10000 ||
            !self::isList($model['history']) ||
            !self::isInteger($model['examples'] ?? null) ||
            $model['examples'] < 0 ||
            (float) $model['examples'] > (float) PHP_INT_MAX ||
            (($model['version'] === 2 || $model['version'] === 2.0) &&
                $model['examples'] < count($model['history']))) {
            return false;
        }

        foreach ($model['weights'] as $row) {
            if (!is_array($row) || count($row) !== self::FEATURE_COUNT + 1 ||
                !self::isList($row)) {
                return false;
            }
            foreach ($row as $weight) {
                if (!is_int($weight) && !is_float($weight)) {
                    return false;
                }
                if (!is_finite((float) $weight)) {
                    return false;
                }
                if (abs((float) $weight) > self::MAX_WEIGHT) {
                    return false;
                }
            }
        }

        foreach ($model['history'] as $note) {
            if (!self::isInteger($note) || $note < 0 || $note >= self::NOTE_COUNT) {
                return false;
            }
        }

        return true;
    }

    public static function isInteger($value): bool
    {
        return is_int($value) ||
            (is_float($value) && is_finite($value) && floor($value) === $value);
    }

    public static function learnFromContext(array &$model, array $context, array $targets): void
    {
        if (!self::isValid($model)) {
            return;
        }

        $context = array_slice($context, -self::CONTEXT_LENGTH);
        $active = [];
        $offset = self::CONTEXT_LENGTH - count($context);
        foreach ($context as $index => $note) {
            if (!self::isInteger($note) || $note < 0 || $note >= self::NOTE_COUNT) {
                return;
            }
            $active[] = ($offset + $index) * self::NOTE_COUNT + (int) $note;
        }
        $active[] = self::FEATURE_COUNT;

        $uniqueTargets = [];
        foreach ($targets as $target) {
            if (!self::isInteger($target) || $target < 0 || $target >= self::NOTE_COUNT) {
                continue;
            }
            $uniqueTargets[(int) $target] = true;
        }
        if (count($uniqueTargets) === 0) {
            return;
        }

        $logits = [];
        foreach ($model['weights'] as $row) {
            $logit = 0.0;
            foreach ($active as $feature) {
                $logit += $row[$feature];
            }
            $logits[] = $logit;
        }

        $scores = [];
        foreach ($logits as $logit) {
            if ($logit >= 0) {
                $scores[] = 1.0 / (1.0 + exp(-$logit));
            } else {
                $exp = exp($logit);
                $scores[] = $exp / (1.0 + $exp);
            }
        }
        foreach ($scores as $note => $score) {
            $error = $score - (isset($uniqueTargets[$note]) ? 1.0 : 0.0);
            foreach ($active as $feature) {
                $updated = $model['weights'][$note][$feature] -
                    self::LEARNING_RATE * $error;
                if (!is_finite((float) $updated)) {
                    $updated = 0.0;
                }
                $model['weights'][$note][$feature] = max(
                    -self::MAX_WEIGHT,
                    min(self::MAX_WEIGHT, (float) $updated)
                );
            }
        }
        $model['examples']++;
    }

    private static function isList(array $values): bool
    {
        $index = 0;
        foreach ($values as $key => $_) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }
        return true;
    }
}
