<?php

namespace App\Http\Controllers\Question;

use App\Http\Controllers\Controller;
use App\Http\Requests\Question\StoreQuestionRequest;
use App\Http\Requests\Question\UpdateQuestionRequest;
use App\Models\Answer;
use App\Models\Question;
use App\Services\QuestionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $questions = Cache::remember('questions', now()->addMinutes(10), function () {
            return Question::with(['user', 'tags'])
                ->withCount('answers')
                ->votes()
                ->orderByDesc('created_at')
                ->paginate(15);
        });

        return view('questions.index', [
            'questions' => $questions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('questions.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreQuestionRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreQuestionRequest $request, QuestionService $questionService)
    {
        Cache::forget('questions');

        $store = $request->safe()
            ->merge([
                'user_id' => auth()->id(),
                'slug' => Str::slug($request->title),
            ]);

        $questionService->createQuestion($store->all());

        return redirect('/questions')->with('message', 'Your question has been submitted');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Question  $question
     * @return \Illuminate\Http\Response
     */
    public function show(Question $question, $slug)
    {
        abort_if(
            $question->slug !== $slug,
            404,
        );

        $question_show = Cache::remember('question-' . $question->id, now()->addMinutes(10), function () use ($question) {
            return Question::with(['user', 'tags'])
                ->votes()
                ->where('id', $question->id)
                ->firstOrFail();
        });

        $answer = Answer::with('user')
            ->votes()
            ->where('question_id', $question->id)
            ->get();

        return view('questions.show', [
            'question' => $question_show,
            'answers' => $answer,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Question  $question
     * @return \Illuminate\Http\Response
     */
    public function edit(Question $question, QuestionService $questionService, $slug)
    {
        abort_if(
            Gate::denies('users-allowed', $question->user_id),
            403
        );

        abort_if(
            $question->slug !== $slug,
            404,
        );

        $question = Question::where('id', $question->id)->firstOrFail();
        $tags = $questionService->editQuestionTag($question->tags);

        return view('questions.edit', [
            'question' => $question,
            'tags' => $tags,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateQuestionRequest  $request
     * @param  \App\Models\Question  $question
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateQuestionRequest $request, Question $question, QuestionService $questionService)
    {
        Gate::authorize('users-allowed', $question->user_id);

        Cache::forget('quesetions');
        Cache::forget('question-' . $question->id);

        $update = $request->safe()->merge([
            'slug' => Str::slug($request->title),
        ]);

        $questionService->updateQuestion($update->all(), $question->id);

        return redirect()
            ->route('question.show', ['question' => $question, 'slug' => $update->slug])
            ->with('message', 'Your question has been updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Question  $question
     * @return \Illuminate\Http\Response
     */
    public function destroy(Question $question)
    {
        Gate::authorize('users-allowed', $question);

        $question->tags()->detach();
        $question->delete();

        Cache::forget('questions');
        Cache::forget('question-' . $question->id);

        return redirect()
            ->route('question.index')
            ->with('message', 'Question deleted successfully!');
    }
}
