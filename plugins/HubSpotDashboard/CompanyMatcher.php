<?php

namespace Piwik\Plugins\HubSpotDashboard;

class CompanyMatcher
{
    private array $ispIndicators = [
        'airtel', 'jio', 'reliance jio', 'vodafone', 'idea', 'bsnl', 'tata communications',
        'google cloud', 'amazon', 'aws', 'microsoft', 'azure', 'cloudflare', 'akamai',
        'digitalocean', 'linode', 'ovh', 'hetzner', 'comcast', 'verizon', 'spectrum',
        'telecom', 'broadband', 'internet service provider', 'isp'
    ];

    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/^www\./', '', $value);
        $value = preg_replace('/[^a-z0-9\.\s-]/', '', $value);
        $value = str_replace([
            ' private limited', ' pvt ltd', ' pvt. ltd.', ' pvt', ' private',
            ' incorporated', ' inc', ' limited', ' ltd', ' llc', ' corporation', ' corp',
            ' technologies', ' technology', ' solutions', ' services'
        ], '', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function isLikelyIsp(string $candidate): bool
    {
        $candidate = $this->normalize($candidate);
        if ($candidate === '' || $candidate === 'unknown') {
            return false;
        }

        foreach ($this->ispIndicators as $indicator) {
            if (str_contains($candidate, $indicator)) {
                return true;
            }
        }

        return false;
    }

    public function match(array $matomoRows, array $hubspotCompanies): array
    {
        $matches = [];

        foreach ($matomoRows as $visit) {
            $rawCandidate = (string)($visit['companyCandidate'] ?? '');
            $candidate = $this->normalize($rawCandidate);
            $candidateIsIsp = $this->isLikelyIsp($rawCandidate);

            $bestCompany = null;
            $bestConfidence = $candidateIsIsp ? 'ISP / Network' : 'Not captured';
            $bestScore = 0;
            $reason = $candidateIsIsp ? 'Candidate looks like ISP/network provider, not confirmed visitor company.' : 'No reliable match found.';

            if (!$candidateIsIsp && $candidate !== '') {
                foreach ($hubspotCompanies as $company) {
                    $p = $company['properties'] ?? [];
                    $name = $this->normalize((string)($p['name'] ?? ''));
                    $domain = $this->normalize((string)($p['domain'] ?? ''));
                    $domainBase = preg_replace('/\.[a-z]{2,}$/', '', $domain);

                    $score = 0;
                    $confidence = 'Not captured';
                    $matchReason = '';

                    if ($domain !== '' && (str_contains($candidate, $domain) || str_contains($domain, $candidate))) {
                        $score = 95;
                        $confidence = 'Strong';
                        $matchReason = 'Domain match';
                    } elseif ($domainBase !== '' && strlen($domainBase) > 3 && str_contains($candidate, $domainBase)) {
                        $score = 88;
                        $confidence = 'Strong';
                        $matchReason = 'Domain-name match';
                    } elseif ($name !== '' && (str_contains($candidate, $name) || str_contains($name, $candidate))) {
                        $score = 82;
                        $confidence = 'Strong';
                        $matchReason = 'Company-name match';
                    } elseif ($name !== '') {
                        similar_text($candidate, $name, $percent);
                        if ($percent >= 72) {
                            $score = (int)$percent;
                            $confidence = 'Medium';
                            $matchReason = 'Fuzzy company-name match';
                        } elseif ($percent >= 50) {
                            $score = (int)$percent;
                            $confidence = 'Weak';
                            $matchReason = 'Low-confidence fuzzy match';
                        }
                    }

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestConfidence = $confidence;
                        $bestCompany = $company;
                        $reason = $matchReason;
                    }
                }
            }

            $companyProperties = $bestCompany['properties'] ?? [];

            $matches[] = [
                'visitTime' => $visit['visitTime'] ?? 'Not captured',
                'visitorId' => $visit['visitorId'] ?? 'Not captured',
                'visitIp' => $visit['visitIp'] ?? 'Hidden / anonymized',
                'country' => $visit['country'] ?? 'Not captured',
                'matomoCompany' => $rawCandidate ?: 'Not captured',
                'pageUrl' => $visit['pageUrl'] ?? 'Not captured',
                'pageTitle' => $visit['pageTitle'] ?? 'Not captured',
                'hubspotCompany' => $companyProperties['name'] ?? 'Not captured',
                'hubspotDomain' => $companyProperties['domain'] ?? 'Not captured',
                'confidence' => $bestConfidence,
                'score' => $bestScore,
                'reason' => $reason
            ];
        }

        return $matches;
    }
}
