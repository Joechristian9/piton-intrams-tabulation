<?php

namespace App\Support;

/**
 * Scoring criteria: the highest score a single judge can give per category.
 * Each round adds up to 100. The judge pages in resources/js use the same
 * numbers for their input limits.
 */
class Criteria
{
    /** Top 3 selection round (total 100). */
    public const SELECTION = [
        'production_number'     => 10,
        'casual_wear'           => 25, // shown as "Sports Wear"
        'swim_wear'             => 25,
        'formal_wear'           => 25,
        'closed_door_interview' => 15,
    ];

    /** Top 3 finals, scored from zero again (total 100). */
    public const FINALS = [
        'face_and_figure' => 50,
        'delivery'        => 40,
        'overall_appeal'  => 10,
    ];

    /** Names shown to people (the keys are DB columns and must not change). */
    public const LABELS = [
        'production_number'     => 'Production Number',
        'casual_wear'           => 'Sports Wear',
        'swim_wear'             => 'Swim Wear',
        'formal_wear'           => 'Formal Wear',
        'closed_door_interview' => 'Casual Interview',
        'face_and_figure'       => 'Beauty of the Face and Figure',
        'delivery'              => 'Delivery',
        'overall_appeal'        => 'Over-all Appeal / X-factor',
    ];

    /** Route name of each category's judge scoring page. */
    public const JUDGE_ROUTES = [
        'production_number'     => 'production_number',
        'casual_wear'           => 'casual_wear',
        'swim_wear'             => 'swim_wear',
        'formal_wear'           => 'formal_wear',
        'closed_door_interview' => 'closed_door_interview',
        'face_and_figure'       => 'beauty_face_figure',
        'delivery'              => 'delivery',
        'overall_appeal'        => 'overall_appeal',
    ];

    public static function max(string $category): int
    {
        return self::SELECTION[$category] ?? self::FINALS[$category];
    }

    public static function isFinals(string $category): bool
    {
        return array_key_exists($category, self::FINALS);
    }
}
