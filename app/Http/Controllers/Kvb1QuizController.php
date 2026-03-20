<?php

namespace App\Http\Controllers;

use App\Actions\Kvb1\QuestionBank;
use App\Http\Requests\Kvb1QuizCheckRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Kvb1QuizController extends Controller
{
    public function __construct(private QuestionBank $questionBank) {}

    public function show(): Response
    {
        return Inertia::render('Kvb1/PracticeQuiz', [
            'meta' => $this->questionBank->meta(),
            'startUrl' => route('kvb1.quiz.start'),
            'checkUrl' => route('kvb1.quiz.check'),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $limit = $request->integer('limit');

        return response()->json([
            'meta' => $this->questionBank->meta(),
            'questions' => $this->questionBank->practiceSet($limit > 0 ? $limit : null),
        ]);
    }

    public function check(Kvb1QuizCheckRequest $request): JsonResponse
    {
        return response()->json(
            $this->questionBank->grade($request->validated('answers')),
        );
    }
}
