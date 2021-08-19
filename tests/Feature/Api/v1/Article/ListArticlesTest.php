<?php

namespace Tests\Feature\Api\v1\Article;

use App\Models\Article;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ListArticlesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Article::factory()->count(30)->create();
    }

    public function testListArticles(): void
    {
        $response = $this->getJson('/api/v1/articles');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) =>
                $json->where('articlesCount', 20)
                    ->has('articles', 20, fn (AssertableJson $item) =>
                        $item->missing('favorited')
                            ->whereAllType([
                                'slug' => 'string',
                                'title' => 'string',
                                'description' => 'string',
                                'body' => 'string',
                                'createdAt' => 'string',
                                'updatedAt' => 'string',
                            ])
                            ->whereAll([
                                'tagList' => [],
                                'favoritesCount' => 0,
                            ])
                            ->has('author', fn (AssertableJson $subItem) =>
                                $subItem->missing('following')
                                    ->whereAllType([
                                        'username' => 'string',
                                        'bio' => 'string|null',
                                        'image' => 'string|null',
                                    ])
                            )
                    )
            );
    }

    public function testListArticlesByTag(): void
    {
        // dummy articles shouldn't be returned
        Article::factory()
            ->has(Tag::factory()->count(3))
            ->count(20)
            ->create();

        /** @var Tag $tag */
        $tag = Tag::factory()
            ->has(Article::factory()->count(10), 'articles')
            ->create();

        $response = $this->getJson("/api/v1/articles?tag={$tag->name}");

        $response->assertOk()
            ->assertJsonPath('articlesCount', 10)
            ->assertJsonCount(10, 'articles');

        // verify has tag
        foreach ($response['articles'] as $article) {
            $this->assertContains(
                $tag->name, Arr::get($article, 'tagList'),
                "Article must have tag {$tag->name}"
            );
        }
    }

    public function testListArticlesByAuthor(): void
    {
        /** @var User $author */
        $author = User::factory()
            ->has(Article::factory()->count(5), 'articles')
            ->create();

        $response = $this->getJson("/api/v1/articles?author={$author->username}");

        $response->assertOk()
            ->assertJsonPath('articlesCount', 5)
            ->assertJsonCount(5, 'articles');

        // verify same author
        foreach ($response['articles'] as $article) {
            $this->assertSame(
                $author->username,
                Arr::get($article, 'author.username'),
                "Author must be {$author->username}."
            );
        }
    }

    public function testListArticlesByFavored(): void
    {
        /** @var User $user */
        $user = User::factory()
            ->has(Article::factory()->count(15), 'favorites')
            ->create();

        $response = $this->getJson("/api/v1/articles?favorited={$user->username}");

        $response->assertOk()
            ->assertJsonPath('articlesCount', 15)
            ->assertJsonCount(15, 'articles');

        // verify favored
        foreach ($response['articles'] as $article) {
            $this->assertSame(
                1, Arr::get($article, 'favoritesCount'),
                "Article must be favored by {$user->username}."
            );
        }
    }

    public function testArticleFeedLimit(): void
    {
        $response = $this->getJson('/api/v1/articles?limit=25');

        $response->assertOk()
            ->assertJsonPath('articlesCount', 25)
            ->assertJsonCount(25, 'articles');
    }

    public function testArticleFeedOffset(): void
    {
        $response = $this->getJson('/api/v1/articles?offset=20');

        $response->assertOk()
            ->assertJsonPath('articlesCount', 10)
            ->assertJsonCount(10, 'articles');
    }

    /**
     * @dataProvider queryProvider
     * @param array<mixed> $data
     * @param array<string> $errors
     */
    public function testArticleListValidation(array $data, array $errors): void
    {
        $response = $this->json('GET', '/api/v1/articles', $data);

        $response->assertStatus(422)
            ->assertInvalid($errors);
    }

    /**
     * @return array<int|string, array<mixed>>
     */
    public function queryProvider(): array
    {
        $errors = ['limit', 'offset'];

        return [
            'not integer' => [['limit' => 'string', 'offset' => 0.123], $errors],
            'less than zero' => [['limit' => -123, 'offset' => -321], $errors],
            'not strings' => [[
                'tag' => 123,
                'author' => [],
                'favorited' => null,
            ], ['tag', 'author', 'favorited']],
        ];
    }
}
