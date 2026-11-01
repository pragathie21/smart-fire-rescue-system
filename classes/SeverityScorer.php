<?php
/**
 * Smart Fire and Rescue Response System
 * SeverityScorer - Transparent Rules-Based Emergency Triage & Decision-Support System
 *
 * NOTE: This is a rules-based academic decision-support algorithm. It is not an
 * artificial intelligence prediction model or a certified emergency dispatch system.
 */

require_once __DIR__ . '/../config.php';

class SeverityScorer {

    /**
     * Compute rules-based incident priority score, severity tier, and reasoning breakdown.
     *
     * @param array $data Assessment form inputs
     * @return array [
     *     'score'       => int (0-100),
     *     'tier'        => string ('Low', 'Medium', 'High', 'Critical'),
     *     'reasons'     => array of string explanations,
     *     'reason_text' => string combined summary
     * ]
     */
    public static function evaluate(array $data): array {
        $weights = defined('SEVERITY_WEIGHTS') ? SEVERITY_WEIGHTS : [];

        $type            = trim($data['incident_type'] ?? 'Fire');
        $fireSize        = trim($data['fire_size'] ?? 'Small');
        $fireSpreading   = !empty($data['fire_spreading']) && ($data['fire_spreading'] == '1' || strtolower((string)$data['fire_spreading']) === 'yes');
        $peopleTrapped   = !empty($data['people_trapped']) && ($data['people_trapped'] == '1' || strtolower((string)$data['people_trapped']) === 'yes');
        $injuriesReported= !empty($data['injuries_reported']) && ($data['injuries_reported'] == '1' || strtolower((string)$data['injuries_reported']) === 'yes');
        $heavySmoke      = !empty($data['heavy_smoke']) && ($data['heavy_smoke'] == '1' || strtolower((string)$data['heavy_smoke']) === 'yes');
        $buildingType    = trim($data['building_type'] ?? 'Other');
        $hazmat          = ucfirst(strtolower(trim($data['hazmat_involved'] ?? 'No')));
        $peopleExposed   = (int)($data['people_exposed'] ?? 0);
        $criticalInfra   = !empty($data['critical_infrastructure']) && ($data['critical_infrastructure'] == '1' || strtolower((string)$data['critical_infrastructure']) === 'yes');

        $score = 0;
        $reasons = [];

        // 1. Base score by incident type
        $typeBase = $weights['type_base'][$type] ?? 20;
        $score += $typeBase;
        $reasons[] = "Base {$type} protocol (+{$typeBase})";

        // 2. Critical Infrastructure (Criteria 1 highest precedence)
        if ($criticalInfra) {
            $infraPts = $weights['critical_infra'] ?? 35;
            $score += $infraPts;
            $reasons[] = "Critical infrastructure impacted (+{$infraPts})";
        }

        // 3. Civilians Trapped (Forces at least HIGH severity)
        if ($peopleTrapped) {
            $trapPts = $weights['people_trapped'] ?? 30;
            $score += $trapPts;
            $reasons[] = "Civilians trapped in structure (+{$trapPts})";
        }

        // 4. Injuries Reported
        if ($injuriesReported) {
            $injPts = $weights['injuries_reported'] ?? 20;
            $score += $injPts;
            $reasons[] = "Active casualties/injuries reported (+{$injPts})";
        }

        // 5. Hazardous Materials
        if ($hazmat === 'Yes') {
            $hazPts = $weights['hazmat_yes'] ?? 25;
            $score += $hazPts;
            $reasons[] = "Hazardous chemical/flammable materials involved (+{$hazPts})";
        } elseif ($hazmat === 'Unknown') {
            $hazPts = $weights['hazmat_unknown'] ?? 10;
            $score += $hazPts;
            $reasons[] = "Unknown chemical presence at risk location (+{$hazPts})";
        }

        // 6. Fire-Specific Questions (Evaluated only if incident type is Fire)
        if ($type === 'Fire') {
            $sizePts = $weights['fire_size'][$fireSize] ?? 5;
            $score += $sizePts;
            $reasons[] = "{$fireSize} fire scale (+{$sizePts})";

            if ($fireSpreading) {
                $spreadPts = $weights['fire_spreading'] ?? 20;
                $score += $spreadPts;
                $reasons[] = "Active fire perimeter spreading rapidly (+{$spreadPts})";
            }

            if ($heavySmoke) {
                $smokePts = $weights['heavy_smoke'] ?? 10;
                $score += $smokePts;
                $reasons[] = "Dense toxic smoke compromising visibility (+{$smokePts})";
            }
        }

        // 7. Building/Location Risk Multiplier
        $bldgPts = $weights['building_type'][$buildingType] ?? 5;
        $score += $bldgPts;
        if ($bldgPts > 5) {
            $reasons[] = "High-occupancy location: {$buildingType} (+{$bldgPts})";
        }

        // 8. Population Exposure
        if ($peopleExposed > 50) {
            $expPts = $weights['population_exposure']['high'] ?? 25;
            $score += $expPts;
            $reasons[] = "Large population exposure (>50 persons, +{$expPts})";
        } elseif ($peopleExposed >= 6) {
            $expPts = $weights['population_exposure']['medium'] ?? 15;
            $score += $expPts;
            $reasons[] = "Moderate population exposure ({$peopleExposed} persons, +{$expPts})";
        } elseif ($peopleExposed >= 1) {
            $expPts = $weights['population_exposure']['low'] ?? 5;
            $score += $expPts;
            $reasons[] = "Low population exposure ({$peopleExposed} persons, +{$expPts})";
        }

        // Cap score at 100
        $finalScore = min(100, max(1, $score));

        // Determine Severity Tier based on thresholds and high-impact conditions
        $criticalThreshold = $weights['thresholds']['critical'] ?? 80;
        $highThreshold     = $weights['thresholds']['high'] ?? 55;
        $mediumThreshold   = $weights['thresholds']['medium'] ?? 30;

        // Forced escalation rules:
        // - Critical: Score >= 80, OR (Critical Infra + (Trapped OR Hazmat)), OR (Trapped + Spreading Fire)
        // - High: Score >= 55, OR People Trapped = true, OR Hazmat = 'Yes', OR (Fire Spreading + Large Fire)
        if ($finalScore >= $criticalThreshold ||
            ($criticalInfra && ($peopleTrapped || $hazmat === 'Yes')) ||
            ($peopleTrapped && $fireSpreading)) {
            $tier = 'Critical';
        } elseif ($finalScore >= $highThreshold || $peopleTrapped || $hazmat === 'Yes' || ($fireSpreading && $fireSize === 'Large')) {
            $tier = 'High';
        } elseif ($finalScore >= $mediumThreshold || $injuriesReported || $fireSize === 'Medium') {
            $tier = 'Medium';
        } else {
            $tier = 'Low';
        }

        $reasonText = implode('; ', $reasons);

        return [
            'score'       => $finalScore,
            'tier'        => $tier,
            'reasons'     => $reasons,
            'reason_text' => $reasonText
        ];
    }
}
