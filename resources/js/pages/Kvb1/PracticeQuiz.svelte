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
    let summary = $state<{
        score: number;
        total_points: number;
        percentage: number;
        passing_score: number;
        passed: boolean;
    } | null>(null);

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
                        40 vragen uit het aangeleverde voorbeeldexamen, inclusief
                        de plaatjes uit de PDF. De volgorde van de vragen en
                        meerkeuze-opties wordt elke keer opnieuw geschud.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-400"
                        onclick={startQuiz}
                        disabled={loading}
                    >
                        {loading ? 'Bezig...' : questions.length === 0
                            ? 'Start oefentoets'
                            : 'Nieuwe toets'}
                    </button>

                    {#if questions.length > 0}
                        <button
                            class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500"
                            onclick={checkQuiz}
                            disabled={checking}
                        >
                            {checking ? 'Nakijken...' : 'Nakijken'}
                        </button>
                    {/if}
                </div>
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

        {#each questions as question, index (question.id)}
            {@const result = results[question.id]}
            <section
                class={`rounded-3xl border bg-white p-6 shadow-sm dark:bg-slate-900 ${
                    result
                        ? result.score === result.punten
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
                            Vraag {index + 1} van {questions.length} · origineel
                            {question.nummer}
                        </p>
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                            {question.vraag}
                        </h2>
                    </div>

                    <div
                        class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        {#if result}
                            {result.score} / {question.punten} punten
                        {:else}
                            {question.punten} punten
                        {/if}
                    </div>
                </div>

                {#if question.afbeelding}
                    <div
                        class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950"
                    >
                        <img
                            src={question.afbeelding}
                            alt={`Afbeelding bij vraag ${question.nummer}`}
                            class="w-full object-contain"
                        />
                    </div>
                {/if}

                {#if question.type === 'mcq' && question.opties}
                    <div class="grid gap-3">
                        {#each question.opties as option (option.id)}
                            <label
                                class={`flex cursor-pointer gap-3 rounded-2xl border px-4 py-3 text-sm transition ${
                                    result
                                        ? result.correct_option === option.id
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100'
                                            : result.selected_option === option.id
                                              ? 'border-rose-300 bg-rose-50 text-rose-950 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100'
                                              : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950'
                                        : 'border-slate-200 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:hover:border-slate-500 dark:hover:bg-slate-950'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name={question.id}
                                    class="mt-0.5"
                                    checked={answers[question.id] === option.id}
                                    onchange={() =>
                                        selectOption(question.id, option.id)}
                                    disabled={Boolean(result)}
                                />
                                <span>{option.tekst}</span>
                            </label>
                        {/each}
                    </div>
                {/if}

                {#if question.type === 'boolean' && question.stellingen}
                    <div class="grid gap-4">
                        {#each question.stellingen as statement (statement.id)}
                            {@const booleanResult =
                                result?.stellingen?.find(
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
                                                name={`${question.id}-${statement.id}`}
                                                checked={(answers[
                                                    question.id
                                                ] as Record<
                                                    string,
                                                    string
                                                >)?.[statement.id] === value}
                                                onchange={() =>
                                                    selectStatementAnswer(
                                                        question.id,
                                                        statement.id,
                                                        value as 'ja' | 'nee',
                                                    )}
                                                disabled={Boolean(result)}
                                            />
                                            <span class="capitalize">{value}</span>
                                        </label>
                                    {/each}
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}

                {#if question.type === 'ordering' && question.items}
                    <div class="grid gap-3">
                        {#each question.items as item}
                            {@const orderingResult =
                                result?.items?.find(
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
                                        (answers[question.id] as Record<
                                            string,
                                            number
                                        >)?.[item] ?? '',
                                    )}
                                    onchange={(event) =>
                                        selectOrderingPosition(
                                            question.id,
                                            item,
                                            (event.currentTarget as HTMLSelectElement)
                                                .value,
                                        )}
                                    disabled={Boolean(result)}
                                >
                                    <option value="">Kies positie</option>
                                    {#each question.items as _, position}
                                        <option value={position + 1}>
                                            {position + 1}
                                        </option>
                                    {/each}
                                </select>
                            </div>
                        {/each}
                    </div>
                {/if}

                {#if result}
                    <div
                        class="mt-5 rounded-2xl bg-slate-100 p-4 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        {#if result.type === 'mcq'}
                            <p>
                                Juiste antwoord:
                                <span class="font-medium">
                                    {result.correct_option?.toUpperCase()}.
                                    {result.correct_option_text}
                                </span>
                            </p>
                        {/if}

                        {#if result.type === 'boolean' && result.stellingen}
                            <div class="grid gap-2">
                                {#each result.stellingen as statement (statement.id)}
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

                        {#if result.type === 'ordering' && result.items}
                            <div class="grid gap-2">
                                {#each result.items as item (item.item)}
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

                        {#if result.uitleg}
                            <p class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-700">
                                <span class="font-medium">Toelichting:</span>
                                {result.uitleg}
                            </p>
                        {/if}
                    </div>
                {/if}
            </section>
        {/each}

        {#if questions.length > 0}
            <div class="flex justify-center pb-8">
                <button
                    class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500"
                    onclick={startQuiz}
                >
                    Opnieuw oefenen
                </button>
            </div>
        {/if}
    </div>
</div>
