<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';

    type QuizMeta = {
        question_count: number;
        total_points: number;
        passing_score: number;
        passing_percentage: number;
    };

    type Option = {
        id: string;
        tekst: string;
    };

    type Statement = {
        id: string;
        tekst: string;
        punten: number;
    };

    type Question = {
        id: string;
        nummer: number;
        vraag: string;
        type: 'mcq' | 'boolean' | 'ordering';
        punten: number;
        uitleg: string | null;
        afbeelding: string | null;
        afbeeldingen?: string[];
        opties?: Option[];
        stellingen?: Statement[];
        items?: string[];
    };

    type Result = {
        id: string;
        type: 'mcq' | 'boolean' | 'ordering';
        score: number;
        punten: number;
        uitleg: string | null;
        correct_option?: string;
        correct_option_text?: string | null;
        selected_option?: string | null;
        stellingen?: {
            id: string;
            tekst: string;
            punten: number;
            score: number;
            selected_answer: string | null;
            correct_answer: string;
        }[];
        items?: {
            item: string;
            expected_position: number;
            selected_position: number | null;
            score: number;
        }[];
    };

    let {
        meta,
        startUrl,
        checkUrl,
    }: {
        meta: QuizMeta;
        startUrl: string;
        checkUrl: string;
    } = $props();

    let loading = $state(false);
    let checking = $state(false);
    let questions = $state<Question[]>([]);
    let answers = $state<Record<string, unknown>>({});
    let results = $state<Record<string, Result>>({});
    let currentIndex = $state(0);
    let summary = $state<{
        score: number;
        total_points: number;
        percentage: number;
        passing_score: number;
        passed: boolean;
    } | null>(null);

    const currentQuestion = $derived(
        questions.length > 0 ? questions[currentIndex] : null,
    );
    const currentResult = $derived(
        currentQuestion ? results[currentQuestion.id] : null,
    );
    const currentImages = $derived(
        currentQuestion
            ? currentQuestion.afbeeldingen &&
                currentQuestion.afbeeldingen.length > 0
                ? currentQuestion.afbeeldingen
                : currentQuestion.afbeelding
                  ? [currentQuestion.afbeelding]
                  : []
            : [],
    );
    const isLastQuestion = $derived(currentIndex === questions.length - 1);
    const isFirstQuestion = $derived(currentIndex === 0);

    function csrfToken(): string {
        return (
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? ''
        );
    }

    async function startQuiz(): Promise<void> {
        loading = true;

        try {
            const response = await fetch(startUrl, {
                headers: {
                    Accept: 'application/json',
                },
            });
            const payload = await response.json();

            questions = payload.questions;
            answers = {};
            results = {};
            summary = null;
            currentIndex = 0;
        } finally {
            loading = false;
        }
    }

    async function checkQuiz(): Promise<void> {
        checking = true;

        try {
            const response = await fetch(checkUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ answers }),
            });
            const payload = await response.json();

            summary = {
                score: payload.score,
                total_points: payload.total_points,
                percentage: payload.percentage,
                passing_score: payload.passing_score,
                passed: payload.passed,
            };

            results = Object.fromEntries(
                payload.results.map((result: Result) => [result.id, result]),
            );
        } finally {
            checking = false;
        }
    }

    function previousQuestion(): void {
        if (currentIndex > 0) {
            currentIndex -= 1;
        }
    }

    function nextQuestion(): void {
        if (currentIndex < questions.length - 1) {
            currentIndex += 1;
        }
    }

    function selectOption(questionId: string, optionId: string): void {
        answers = {
            ...answers,
            [questionId]: optionId,
        };
    }

    function selectStatementAnswer(
        questionId: string,
        statementId: string,
        value: 'ja' | 'nee',
    ): void {
        const current = (answers[questionId] as Record<string, string>) ?? {};

        answers = {
            ...answers,
            [questionId]: {
                ...current,
                [statementId]: value,
            },
        };
    }

    function selectOrderingPosition(
        questionId: string,
        item: string,
        position: string,
    ): void {
        const current = (answers[questionId] as Record<string, number>) ?? {};

        answers = {
            ...answers,
            [questionId]: {
                ...current,
                [item]: Number(position),
            },
        };
    }

    function answerLabel(value: string | null | undefined): string {
        if (value === 'ja') {
            return 'Ja';
        }

        if (value === 'nee') {
            return 'Nee';
        }

        return value ?? 'Geen antwoord';
    }
</script>

<AppHead title="KVB1 Oefentoets" />

<div class="min-h-screen bg-slate-50 py-8 dark:bg-slate-950">
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4">
        <section
            class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="space-y-2">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500"
                    >
                        Klein Vaarbewijs 1
                    </p>
                    <h1 class="text-3xl font-semibold text-slate-900 dark:text-white">
                        Oefentoets
                    </h1>
                    <p class="max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                        Je krijgt steeds één vraag tegelijk. Aan het einde kun je
                        alles in één keer nakijken.
                    </p>
                </div>

                <button
                    class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-400"
                    onclick={startQuiz}
                    disabled={loading}
                >
                    {loading ? 'Bezig...' : questions.length === 0
                        ? 'Start oefentoets'
                        : 'Nieuwe toets'}
                </button>
            </div>

            <div
                class="mt-6 grid gap-3 text-sm text-slate-700 md:grid-cols-4 dark:text-slate-200"
            >
                <div class="rounded-2xl bg-slate-100 px-4 py-3 dark:bg-slate-800">
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Vragen
                    </div>
                    <div class="mt-1 text-xl font-semibold">
                        {meta.question_count}
                    </div>
                </div>
                <div class="rounded-2xl bg-slate-100 px-4 py-3 dark:bg-slate-800">
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Max punten
                    </div>
                    <div class="mt-1 text-xl font-semibold">
                        {meta.total_points}
                    </div>
                </div>
                <div class="rounded-2xl bg-slate-100 px-4 py-3 dark:bg-slate-800">
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Slaggrens
                    </div>
                    <div class="mt-1 text-xl font-semibold">
                        {meta.passing_score}
                    </div>
                </div>
                <div class="rounded-2xl bg-slate-100 px-4 py-3 dark:bg-slate-800">
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Minimaal
                    </div>
                    <div class="mt-1 text-xl font-semibold">
                        {meta.passing_percentage}%
                    </div>
                </div>
            </div>
        </section>

        {#if summary}
            <section
                class={`rounded-3xl border p-6 shadow-sm ${
                    summary.passed
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100'
                        : 'border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-100'
                }`}
            >
                <div
                    class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h2 class="text-2xl font-semibold">
                            {summary.passed ? 'Geslaagd' : 'Nog niet geslaagd'}
                        </h2>
                        <p class="mt-1 text-sm opacity-80">
                            Je hebt {summary.score} van de {summary.total_points}
                            punten gehaald.
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-semibold">
                            {summary.percentage}%
                        </div>
                        <div class="text-sm opacity-80">
                            Slaggrens: {summary.passing_score} punten
                        </div>
                    </div>
                </div>
            </section>
        {/if}

        {#if questions.length === 0}
            <section
                class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"
            >
                Klik op <span class="font-semibold">Start oefentoets</span> om
                direct te oefenen met de 40 vragen uit de PDF.
            </section>
        {/if}

        {#if currentQuestion}
            <section
                class={`rounded-3xl border bg-white p-6 shadow-sm dark:bg-slate-900 ${
                    currentResult
                        ? currentResult.score === currentResult.punten
                            ? 'border-emerald-300 dark:border-emerald-800'
                            : 'border-rose-300 dark:border-rose-800'
                        : 'border-slate-200 dark:border-slate-800'
                }`}
            >
                <div
                    class="mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                >
                    <div class="space-y-2">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400"
                        >
                            Vraag {currentIndex + 1} van {questions.length} ·
                            origineel {currentQuestion.nummer}
                        </p>
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                            {currentQuestion.vraag}
                        </h2>
                    </div>

                    <div
                        class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        {#if currentResult}
                            {currentResult.score} / {currentQuestion.punten}
                            punten
                        {:else}
                            {currentQuestion.punten} punten
                        {/if}
                    </div>
                </div>

                {#if currentImages.length > 0}
                    <div
                        class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-950"
                    >
                        {#each currentImages as image, imageIndex (image)}
                            <img
                                src={image}
                                alt={`Afbeelding ${imageIndex + 1} bij vraag ${currentQuestion.nummer}`}
                                class="w-full rounded-xl bg-white object-contain"
                            />
                        {/each}
                    </div>
                {/if}

                {#if currentQuestion.type === 'mcq' && currentQuestion.opties}
                    <div class="grid gap-3">
                        {#each currentQuestion.opties as option (option.id)}
                            <label
                                class={`flex cursor-pointer gap-3 rounded-2xl border px-4 py-3 text-sm transition ${
                                    currentResult
                                        ? currentResult.correct_option ===
                                            option.id
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100'
                                            : currentResult.selected_option ===
                                                  option.id
                                              ? 'border-rose-300 bg-rose-50 text-rose-950 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100'
                                              : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950'
                                        : 'border-slate-200 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:hover:border-slate-500 dark:hover:bg-slate-950'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name={currentQuestion.id}
                                    class="mt-0.5"
                                    checked={answers[currentQuestion.id] ===
                                        option.id}
                                    onchange={() =>
                                        selectOption(
                                            currentQuestion.id,
                                            option.id,
                                        )}
                                    disabled={Boolean(currentResult)}
                                />
                                <span>{option.tekst}</span>
                            </label>
                        {/each}
                    </div>
                {/if}

                {#if currentQuestion.type === 'boolean' &&
                    currentQuestion.stellingen}
                    <div class="grid gap-4">
                        {#each currentQuestion.stellingen as statement (statement.id)}
                            {@const booleanResult =
                                currentResult?.stellingen?.find(
                                    ({ id }) => id === statement.id,
                                )}
                            <div
                                class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <div
                                    class="mb-3 flex items-center justify-between gap-3"
                                >
                                    <p
                                        class="text-sm font-medium text-slate-900 dark:text-white"
                                    >
                                        {statement.tekst}
                                    </p>
                                    <span
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {statement.punten} punt
                                    </span>
                                </div>

                                <div class="flex flex-wrap gap-3">
                                    {#each ['ja', 'nee'] as value}
                                        <label
                                            class={`flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2 text-sm ${
                                                booleanResult
                                                    ? booleanResult.correct_answer ===
                                                        value
                                                        ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/40'
                                                        : booleanResult.selected_answer ===
                                                            value
                                                          ? 'border-rose-300 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/40'
                                                          : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950'
                                                    : 'border-slate-200 hover:border-slate-400 dark:border-slate-700 dark:hover:border-slate-500'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name={`${currentQuestion.id}-${statement.id}`}
                                                checked={(answers[
                                                    currentQuestion.id
                                                ] as Record<
                                                    string,
                                                    string
                                                >)?.[statement.id] === value}
                                                onchange={() =>
                                                    selectStatementAnswer(
                                                        currentQuestion.id,
                                                        statement.id,
                                                        value as 'ja' | 'nee',
                                                    )}
                                                disabled={Boolean(currentResult)}
                                            />
                                            <span class="capitalize">{value}</span>
                                        </label>
                                    {/each}
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}

                {#if currentQuestion.type === 'ordering' && currentQuestion.items}
                    <div class="grid gap-3">
                        {#each currentQuestion.items as item}
                            {@const orderingResult =
                                currentResult?.items?.find(
                                    ({ item: current }) => current === item,
                                )}
                            <div
                                class="flex flex-col gap-3 rounded-2xl border border-slate-200 p-4 md:flex-row md:items-center md:justify-between dark:border-slate-700"
                            >
                                <div>
                                    <p
                                        class="text-sm font-medium text-slate-900 dark:text-white"
                                    >
                                        {item}
                                    </p>
                                    {#if orderingResult}
                                        <p
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Juiste positie:
                                            {orderingResult.expected_position}
                                        </p>
                                    {/if}
                                </div>

                                <select
                                    class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                                    value={String(
                                        (answers[currentQuestion.id] as Record<
                                            string,
                                            number
                                        >)?.[item] ?? '',
                                    )}
                                    onchange={(event) =>
                                        selectOrderingPosition(
                                            currentQuestion.id,
                                            item,
                                            (event.currentTarget as HTMLSelectElement)
                                                .value,
                                        )}
                                    disabled={Boolean(currentResult)}
                                >
                                    <option value="">Kies positie</option>
                                    {#each currentQuestion.items as _, position}
                                        <option value={position + 1}>
                                            {position + 1}
                                        </option>
                                    {/each}
                                </select>
                            </div>
                        {/each}
                    </div>
                {/if}

                {#if currentResult}
                    <div
                        class="mt-5 rounded-2xl bg-slate-100 p-4 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        {#if currentResult.type === 'mcq'}
                            <p>
                                Juiste antwoord:
                                <span class="font-medium">
                                    {currentResult.correct_option?.toUpperCase()}.
                                    {currentResult.correct_option_text}
                                </span>
                            </p>
                        {/if}

                        {#if currentResult.type === 'boolean' &&
                            currentResult.stellingen}
                            <div class="grid gap-2">
                                {#each currentResult.stellingen as statement (statement.id)}
                                    <p>
                                        <span class="font-medium">
                                            {statement.tekst}
                                        </span>
                                        · jouw antwoord:
                                        {answerLabel(statement.selected_answer)}
                                        · juist:
                                        {answerLabel(statement.correct_answer)}
                                    </p>
                                {/each}
                            </div>
                        {/if}

                        {#if currentResult.type === 'ordering' &&
                            currentResult.items}
                            <div class="grid gap-2">
                                {#each currentResult.items as item (item.item)}
                                    <p>
                                        <span class="font-medium">
                                            {item.item}
                                        </span>
                                        · jouw positie:
                                        {item.selected_position ?? 'geen'}
                                        · juiste positie:
                                        {item.expected_position}
                                    </p>
                                {/each}
                            </div>
                        {/if}

                        {#if currentResult.uitleg}
                            <p class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-700">
                                <span class="font-medium">Toelichting:</span>
                                {currentResult.uitleg}
                            </p>
                        {/if}
                    </div>
                {/if}

                <div
                    class="mt-6 flex flex-col gap-3 border-t border-slate-200 pt-6 md:flex-row md:items-center md:justify-between dark:border-slate-700"
                >
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Gebruik vorige/volgende om vraag voor vraag door de toets
                        te lopen.
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500"
                            onclick={previousQuestion}
                            disabled={isFirstQuestion}
                        >
                            Vorige vraag
                        </button>

                        {#if isLastQuestion}
                            <button
                                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-400"
                                onclick={checkQuiz}
                                disabled={checking || summary !== null}
                            >
                                {checking ? 'Nakijken...' : summary
                                    ? 'Nagekeken'
                                    : 'Nakijken'}
                            </button>
                        {:else}
                            <button
                                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-400"
                                onclick={nextQuestion}
                            >
                                Volgende vraag
                            </button>
                        {/if}
                    </div>
                </div>
            </section>
        {/if}
    </div>
</div>
