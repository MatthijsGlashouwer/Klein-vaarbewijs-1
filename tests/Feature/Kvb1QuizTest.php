<?php

it('shows the kvb1 practice page', function () {
    $this->get('/oefentoets/kvb1')->assertSuccessful();
});

it('returns a quiz payload without revealing the answers', function () {
    $response = $this->getJson('/oefentoets/kvb1/start');

    $response
        ->assertSuccessful()
        ->assertJsonPath('meta.question_count', 40)
        ->assertJsonPath('meta.total_points', 80)
        ->assertJsonCount(40, 'questions')
        ->assertJsonMissingPath('questions.0.juiste_antwoord');
});

it('scores submitted answers', function () {
    $response = $this->postJson('/oefentoets/kvb1/check', [
        'answers' => [
            'kvb1-01' => 'b',
            'kvb1-11' => [
                'Nel' => 1,
                'Kim' => 2,
                'Han' => 3,
            ],
            'kvb1-13' => [
                'i' => 'nee',
                'ii' => 'ja',
            ],
        ],
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('score', 6)
        ->assertJsonPath('total_points', 80)
        ->assertJsonPath('passed', false)
        ->assertJsonPath('results.0.id', 'kvb1-01');
});
