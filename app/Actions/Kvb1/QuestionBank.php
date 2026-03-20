<?php

namespace App\Actions\Kvb1;

use Illuminate\Support\Collection;
use RuntimeException;

class QuestionBank
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $path = storage_path('app/private/kvb1/questions.json');

        if (! is_file($path)) {
            throw new RuntimeException('KVB1-vragenbank niet gevonden. Draai eerst php artisan app:import-kvb1-exam.');
        }

        $questions = json_decode((string) file_get_contents($path), true);

        if (! is_array($questions)) {
            throw new RuntimeException('KVB1-vragenbank kon niet worden geladen.');
        }

        return $questions;
    }

    /**
     * @return array{
     *     question_count: int,
     *     total_points: int,
     *     passing_score: int,
     *     passing_percentage: int
     * }
     */
    public function meta(): array
    {
        $questions = $this->all();
        $totalPoints = array_sum(array_map(
            static fn (array $question): int => (int) $question['punten'],
            $questions,
        ));

        return [
            'question_count' => count($questions),
            'total_points' => $totalPoints,
            'passing_score' => 56,
            'passing_percentage' => (int) round((56 / max($totalPoints, 1)) * 100),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function practiceSet(?int $limit = null): array
    {
        $questions = Collection::make($this->all())->shuffle()->values();

        if ($limit !== null && $limit > 0) {
            $questions = $questions->take($limit)->values();
        }

        return $questions
            ->map(fn (array $question): array => $this->sanitizeQuestion($question))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array{
     *     score: int,
     *     total_points: int,
     *     percentage: int,
     *     passing_score: int,
     *     passed: bool,
     *     results: array<int, array<string, mixed>>
     * }
     */
    public function grade(array $answers): array
    {
        $questions = $this->all();
        $meta = $this->meta();
        $results = [];
        $score = 0;

        foreach ($questions as $question) {
            $submitted = $answers[$question['id']] ?? null;
            $result = match ($question['type']) {
                'boolean' => $this->gradeBoolean($question, $submitted),
                'ordering' => $this->gradeOrdering($question, $submitted),
                default => $this->gradeMcq($question, $submitted),
            };

            $score += $result['score'];
            $results[] = $result;
        }

        return [
            'score' => $score,
            'total_points' => $meta['total_points'],
            'percentage' => (int) round(($score / max($meta['total_points'], 1)) * 100),
            'passing_score' => $meta['passing_score'],
            'passed' => $score >= $meta['passing_score'],
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function sanitizeQuestion(array $question): array
    {
        if ($question['type'] === 'mcq') {
            $question['opties'] = Collection::make($question['opties'])->shuffle()->values()->all();
            unset($question['juiste_antwoord']);
        }

        if ($question['type'] === 'boolean') {
            foreach ($question['stellingen'] as $index => $statement) {
                unset($question['stellingen'][$index]['antwoord']);
            }
        }

        if ($question['type'] === 'ordering') {
            unset($question['antwoord']);
        }

        return $question;
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function gradeMcq(array $question, mixed $submitted): array
    {
        $selected = is_string($submitted) ? $submitted : null;
        $correct = (string) $question['juiste_antwoord'];
        $score = $selected === $correct ? (int) $question['punten'] : 0;

        return [
            'id' => $question['id'],
            'nummer' => $question['nummer'],
            'type' => $question['type'],
            'vraag' => $question['vraag'],
            'punten' => (int) $question['punten'],
            'score' => $score,
            'afbeelding' => $question['afbeelding'],
            'uitleg' => $question['uitleg'],
            'selected_option' => $selected,
            'correct_option' => $correct,
            'correct_option_text' => $this->findOptionText($question, $correct),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function gradeBoolean(array $question, mixed $submitted): array
    {
        $submittedAnswers = is_array($submitted) ? $submitted : [];
        $score = 0;
        $results = [];

        foreach ($question['stellingen'] as $statement) {
            $selected = $submittedAnswers[$statement['id']] ?? null;
            $earned = $selected === $statement['antwoord'] ? (int) $statement['punten'] : 0;
            $score += $earned;

            $results[] = [
                'id' => $statement['id'],
                'tekst' => $statement['tekst'],
                'punten' => (int) $statement['punten'],
                'score' => $earned,
                'selected_answer' => $selected,
                'correct_answer' => $statement['antwoord'],
            ];
        }

        return [
            'id' => $question['id'],
            'nummer' => $question['nummer'],
            'type' => $question['type'],
            'vraag' => $question['vraag'],
            'punten' => (int) $question['punten'],
            'score' => $score,
            'afbeelding' => $question['afbeelding'],
            'uitleg' => $question['uitleg'],
            'stellingen' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function gradeOrdering(array $question, mixed $submitted): array
    {
        $submittedPositions = is_array($submitted) ? $submitted : [];
        $score = 0;
        $results = [];

        foreach ($question['items'] as $index => $item) {
            $expectedPosition = $index + 1;
            $selectedPosition = isset($submittedPositions[$item]) ? (int) $submittedPositions[$item] : null;
            $earned = $selectedPosition === $expectedPosition ? 1 : 0;
            $score += $earned;

            $results[] = [
                'item' => $item,
                'expected_position' => $expectedPosition,
                'selected_position' => $selectedPosition,
                'score' => $earned,
            ];
        }

        return [
            'id' => $question['id'],
            'nummer' => $question['nummer'],
            'type' => $question['type'],
            'vraag' => $question['vraag'],
            'punten' => (int) $question['punten'],
            'score' => $score,
            'afbeelding' => $question['afbeelding'],
            'uitleg' => $question['uitleg'],
            'items' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function findOptionText(array $question, string $optionId): ?string
    {
        foreach ($question['opties'] as $option) {
            if ($option['id'] === $optionId) {
                return $option['tekst'];
            }
        }

        return null;
    }
}
