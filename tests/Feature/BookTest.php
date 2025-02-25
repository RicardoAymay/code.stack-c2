<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Http\UploadedFile;

uses()->group('books');

function getLastBook(): Book
{
    return Book::query()->with(['cover', 'author', 'genre'])->latest()->first();
}

test('should return a list of paginated books', function () {
   
    $concreteBook = Book::factory()->create();
    $response = $this->getJson(route('books.index'));
    $paginatedResponse = $response->json();

    expect($paginatedResponse)
        ->toBePaginated()
        ->and($paginatedResponse['data'])
        ->toHaveCount(1)
        ->sequence(
            fn(\Pest\Expectation $book) => $book
                ->toMatchArray([
                    'id' => $concreteBook->getKey(),
                    'title' => $concreteBook->title,
                    'rating' => $concreteBook->rating,
                    'author' => [
                        'id' => $concreteBook->author->getKey(),
                        'name' => $concreteBook->author->name,
                    ],
                    'genre' => [
                        'id' => $concreteBook->genre->getKey(),
                        'name' => $concreteBook->genre->name,
                    ],
                    'cover' => [
                        'id' => $concreteBook->cover->getKey(),
                        'url' => url($concreteBook->cover->path),
                    ],
                ]),
        );

});

test('will fail when creating a book with invalid data', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user, 'sanctum')->postJson(route('books.store'), [
        'title' => '',
        'description' => '',
        'author' => '',
        'genre_id' => '',
    ]);

    $response->assertUnprocessable();
});

test('can create a book without cover', function () {
    $user = User::factory()->create();
    $author = Author::factory()->create();
    $genre = Genre::factory()->create();
    $title = fake()->word;
    $description = fake()->paragraph;
    $rating = fake()->numberBetween(1, 5);
    $isbn = fake()->isbn13();
    $arrData = [
        'title' => $title,
        'description' => $description,
        'rating' => $rating,
        'isbn' => $isbn,
        'author_id' => $author->getKey(),
        'genre_id' => $genre->getKey(),
    ];

    $response = $this->actingAs($user, 'sanctum')->postJson(route('books.store'), $arrData);
    $book = getLastBook();

    $response->assertCreated();
    expect($book)->toBeBook($arrData)
        ->and($book->cover)->toBeNull();
});

test('can create a book with cover', function () {
    $user = User::factory()->create(); 
    $author = Author::factory()->create();
    $genre = Genre::factory()->create();
    $title = fake()->words(asText: true);
    $description = fake()->paragraph;
    $rating = fake()->numberBetween(1, 5);
    $isbn = fake()->isbn13();

    
    $coverResponse = $this->actingAs($user, 'sanctum')->postJson(route('covers.store'), [
        'cover' => UploadedFile::fake()->image('image.jpg'),
    ]);
    $coverResponse->assertCreated();
    $coverData = $coverResponse->json()['data'];

   
    $arrData = [
        'title' => $title,
        'description' => $description,
        'rating' => $rating,
        'isbn' => $isbn,
        'author_id' => $author->getKey(),
        'genre_id' => $genre->getKey(),
        'cover_id' => $coverData['id'],
    ];

    $response = $this->actingAs($user, 'sanctum')->postJson(route('books.store'), $arrData);
    $response->assertCreated();
    $book = getLastBook();

    $response->assertCreated();
    expect($book)->toBeBook($arrData)
        ->and($book->cover_id)->toBe($coverData['id']);
});


test('can show a book correctly', function () {
    $book = Book::factory()->create();
    $creationResponse = $this->getJson(route('books.show', $book->getKey()));
    $bookData = $creationResponse->json();

    $creationResponse->assertOk();
    expect($bookData['data'])
        ->toMatchArray([
            'id' => $book->getKey(),
            'title' => $book->title,
            'description' => $book->description,
            'rating' => $book->rating,
            'isbn' => $book->isbn,
            'author' => [
                'id' => $book->author->getKey(),
                'name' => $book->author->name,
            ],
            'genre' => [
                'id' => $book->genre->getKey(),
                'name' => $book->genre->name,
            ],
            'cover' => [
                'id' => $book->cover->getKey(),
                'url' => url($book->cover->path),
            ],
        ]);
});

test('will fail when updating a book with invalid data', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();
    $response = $this->actingAs($user, 'sanctum')->putJson(route('books.update', $book->getKey()), [
        'title' => '',
        'description' => '',
        'author' => '',
        'genre_id' => '',
    ]);

    $response->assertUnprocessable();
});

test('can update a book', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();
    $author = Author::factory()->create();
    $genre = Genre::factory()->create();
    $title = fake()->words(asText: true);
    $description = fake()->paragraph;
    $rating = fake()->numberBetween(1, 5);
    $isbn = fake()->isbn13();
    $arrData = [
        'title' => $title,
        'description' => $description,
        'cover_id' => $book->cover_id,
        'rating' => $rating,
        'isbn' => $isbn,
        'author_id' => $author->getKey(),
        'genre_id' => $genre->getKey(),
    ];
    $response = $this->actingAs($user, 'sanctum')->putJson(route('books.update', $book->getKey()), $arrData);
    $response->assertOk();

    $updatedBook = $book->fresh();

    $response->assertOk();
    Storage::disk()->assertExists($updatedBook->cover->path);

    expect($updatedBook)->toBeBook($arrData)
        ->and($updatedBook->id)->toBe($book->getKey())
        ->and($updatedBook->cover_id)->toBe($book->cover_id);
});

test('can remove a book cover', function () {
    $user = User::factory()->create(); 
    $book = Book::factory()->create(); 
    $coverPath = $book->cover->path; 

    $response = $this->actingAs($user, 'sanctum')->delete(route('books.cover.destroy', [$book->getKey(), $book->cover_id]));

    $response->assertNoContent(); 
    Storage::disk()->assertMissing($coverPath); 
});

afterAll(function () {
    exec('rm ' . storage_path('app/files') . '/temp*'); // Cleanup temporary files
});
