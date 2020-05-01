<?php

namespace App\Repositories;

use App\Repositories\Interfaces\StoryRepositoryInterface;
use App\UnconfirmedStory;
use App\FunnyEvent;
use App\SeekAdvice;
use App\Confession;
use Illuminate\Http\Request;

class StoryRepository implements StoryRepositoryInterface
{
    public $storyTypes;

    public function __construct ()
    {
        $this->storyTypes = collect(['funny-events' => FunnyEvent::class, 'seek-advice' => SeekAdvice::class, 'confessions' => Confession::class]);
    }

    #region UnconfirmedStories

    public function allUnconfirmedStories()
    {
        return UnconfirmedStory::oldest()->get();
    }

    public function createUnconfirmedStory(Request $request)
    {
        $request->validate(
        [
            'text' => ['required', 'min:10', 'max:1500'],
            'type' => ['required']
        ]);
        return UnconfirmedStory::create($request->all());
    }

    public function updateUnconfirmedStory(Request $request)
    {
        $story = UnconfirmedStory::find($request->id);

        $story->approvals += $request->approve;
        $story->disapprovals += $request->disapprove;
        $story->reports += $request->report;

        if ($story->approvals >= 20 || $story->approvals - $story->disapprovals >= 10) {
            if ($this->createStory($story->text, $story->author, $story->type, $story->tags)) {
                return $story->delete();
            }
        }
        else if ($story->disapprovals >= 20 || $story->disapprovals - $story->approvals >= 10 || $story->reports >= 5) {
            return $story->delete();
        }
        else return $story->save();
    }

    #endregion UnconfirmedStories


    #region Get

    public function allRecent($model)
    {
        return $model::latest()->get();
    }
    
    public function allPopular($model)
    {
        return $model::orderBy('popularity', 'desc')->get();
    }

    #endregion Get


    #region Create/Update

    public function createStory($text, $author, $type, $tags)
    {
        $model = $this->storyTypes->get($type);
        
        $story = new $model;
        $story->text = $text;
        $story->author = $author;
        $story->tags = $tags;
        return $story->save();
    }

    public function updateStory(Request $request, $model)
    {
        $story = $model::find($request->id);

        $newRating = $request->rating;
        if ($newRating > 0)
        {
            $story->increment('number_of_ratings');
            $story->rating_sum += $newRating;
            $story->rating = round($story->rating_sum / $story->number_of_ratings, 2);
        }
        else {
            $story->approvals += $request->approve;
            $story->disapprovals += $request->disapprove;
        }
        $story->popularity = round($story->rating * ($story->approvals + $story->disapprovals / 2 + $story->number_of_ratings) + $story->number_of_comments * 2, 2);

        return $story->save();
    }
    
    #endregion Create/Update
    
}