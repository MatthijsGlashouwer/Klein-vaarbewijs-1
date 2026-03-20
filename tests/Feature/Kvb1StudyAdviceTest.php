<?php

function correctKvb1Answers(): array
{
    $questions = json_decode(
        (string) file_get_contents(storage_path('app/private/kvb1/questions.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $answers = [];

    foreach ($questions as $question) {
        if ($question['type'] === 'mcq') {
            $answers[$question['id']] = $question['juiste_antwoord'];

            continue;
        }

        if ($question['type'] === 'boolean') {
            $answers[$question['id']] = collect($question['stellingen'])
                ->mapWithKeys(fn (array $statement): array => [$statement['id'] => $statement['antwoord']])
                ->all();

            continue;
        }

        $answers[$question['id']] = collect($question['items'])
            ->values()
            ->mapWithKeys(fn (string $item, int $index): array => [$item => $index + 1])
            ->all();
    }

    return $answers;
}

it('returns study advice based on weak topics', function () {
    $answers = correctKvb1Answers();

    $answers['kvb1-29'] = 'b';
    $answers['kvb1-30'] = 'a';

    $response = $this->postJson('/oefentoets/kvb1/check', [
        'answers' => $answers,
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('score', 77)
        ->assertJsonCount(1, 'study_advice')
        ->assertJsonPath('study_advice.0.chapter', 'Hoofdstuk 1')
        ->assertJsonPath('study_advice.0.section', '1.7.1')
        ->assertJsonPath('study_advice.0.title', 'Meteorologie')
        ->assertJsonPath('study_advice.0.questions.0', 29)
        ->assertJsonPath('study_advice.0.questions.1', 30);
});
