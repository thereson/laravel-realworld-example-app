<?php

namespace App\Http\Controllers\Api\v1\Articles;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArticleListRequest;
use App\Http\Requests\FeedRequest;
use App\Http\Requests\NewArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\ArticlesCollection;
use App\Models\Article;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class ArticleController extends Controller
{
    /**
     * @var int Default limit for feed listing.
     */
    protected const FILTER_LIMIT = 20;

    /***
     * @var int Default offset for feed listing.
     */
    protected const FILTER_OFFSET = 0;

    /**
     * Display global listing of the articles.
     *
     * @param \App\Http\Requests\ArticleListRequest $request
     * @return \App\Http\Resources\ArticlesCollection<Article>
     */
    public function list(ArticleListRequest $request)
    {
        $filter = collect($request->validated());

        $limit = (int) $filter->get('limit', static::FILTER_LIMIT);
        $offset = (int) $filter->get('offset', static::FILTER_OFFSET);

        $list = Article::orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset);

        if ($tag = $filter->get('tag')) {
            $list->whereHas('tags', function (Builder $query) use ($tag) {
                $query->where('name', $tag);
            });
        }

        if ($authorName = $filter->get('author')) {
            $list->whereHas('author', function (Builder $query) use ($authorName) {
                $query->where('name', $authorName);
            });
        }

        if ($userName = $filter->get('favorited')) {
            $list->whereHas('favoredUsers', function (Builder $query) use ($userName) {
                $query->where('name', $userName);
            });
        }

        return new ArticlesCollection($list->get());
    }

    /**
     * Display article feed for the user.
     *
     * @param \App\Http\Requests\FeedRequest $request
     * @return \App\Http\Resources\ArticlesCollection<Article>
     */
    public function feed(FeedRequest $request)
    {
        $filter = collect($request->validated());

        $limit = (int) $filter->get('limit', static::FILTER_LIMIT);
        $offset = (int) $filter->get('offset', static::FILTER_OFFSET);

        /**
         * @var \App\Models\User $user
         */
        $user = $request->user();

        $feed = Article::whereHas('author',
            function (Builder $query) use ($user) {
                $query->whereIn('id', $user->authors->pluck('id'));
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return new ArticlesCollection($feed);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\NewArticleRequest $request
     * @return \App\Http\Resources\ArticleResource
     */
    public function create(NewArticleRequest $request)
    {
        $attributes = Arr::get($request->validated(), 'article');
        $tags = Arr::pull($attributes, 'tagList');

        $article = Article::create($attributes);

        if (is_array($tags)) {
            foreach ($tags as $tagName) {
                $tag = Tag::firstOrCreate([
                    'name' => $tagName,
                ]);

                $article->tags()->attach($tag);
            }

            $article->refresh();
        }

        return new ArticleResource($article);
    }

    /**
     * Display the specified resource.
     *
     * @param string $slug
     * @return \App\Http\Resources\ArticleResource
     */
    public function show(string $slug)
    {
        $article = Article::whereSlug($slug)
            ->firstOrFail();

        return new ArticleResource($article);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\UpdateArticleRequest $request
     * @param string $slug
     * @return \App\Http\Resources\ArticleResource
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(UpdateArticleRequest $request, string $slug)
    {
        $article = Article::whereSlug($slug)
            ->firstOrFail();

        $this->authorize('update', $article);

        $attributes = Arr::get($request->validated(), 'article');

        $article->update($attributes);

        return new ArticleResource($article);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function delete(string $slug)
    {
        $article = Article::whereSlug($slug)
            ->firstOrFail();

        $this->authorize('delete', $article);

        $article->delete(); // cascade

        return response()->json(['status' => 'success']);
    }
}