<?php

namespace Poniverse\Ponyfm\Commands;

use Poniverse\Ponyfm\Album;
use Poniverse\Ponyfm\Comment;
use Poniverse\Ponyfm\Playlist;
use Poniverse\Ponyfm\Track;
use Poniverse\Ponyfm\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CreateCommentCommand extends CommandBase
{
    private $_input;
    private $_id;
    private $_type;

    function __construct($type, $id, $input)
    {
        $this->_input = $input;
        $this->_id = $id;
        $this->_type = $type;
    }

    /**
     * @return bool
     */
    public function authorize()
    {
        $user = \Auth::user();

        return $user != null;
    }

    /**
     * @throws \Exception
     * @return CommandResponse
     */
    public function execute()
    {
        $rules = [
            'content' => 'required',
            'track_id' => 'exists:tracks,id',
            'albums_id' => 'exists:albums,id',
            'playlist_id' => 'exists:playlists,id',
            'profile_id' => 'exists:users,id',
        ];

        $validator = Validator::make($this->_input, $rules);

        if ($validator->fails()) {
            return CommandResponse::fail($validator);
        }

        $comment = new Comment();
        $comment->user_id = Auth::user()->id;
        $comment->content = $this->_input['content'];

        if ($this->_type == 'track') {
            $column = 'track_id';
        } else {
            if ($this->_type == 'user') {
                $column = 'profile_id';
            } else {
                if ($this->_type == 'album') {
                    $column = 'album_id';
                } else {
                    if ($this->_type == 'playlist') {
                        $column = 'playlist_id';
                    } else {
                        App::abort(500);
                    }
                }
            }
        }

        $comment->$column = $this->_id;
        $comment->save();

	    // Recount the track's comments, if this is a track comment
	    if ($this->_type === 'track') {
		    $entity = Track::find($this->_id);

	    } elseif ($this->_type === 'album') {
		    $entity = Album::find($this->_id);

	    } elseif ($this->_type === 'playlist') {
		    $entity = Playlist::find($this->_id);

	    } elseif ($this->_type === 'user') {
		    $entity = User::find($this->_id);

	    } else {
		    App::abort(400, 'This comment is being added to an invalid entity!');
	    }

	    $entity->comment_count = Comment::where($column, $this->_id)->count();
	    $entity->save();

        return CommandResponse::succeed(Comment::mapPublic($comment));
    }
}
