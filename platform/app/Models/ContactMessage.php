<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Private messages received through the public contact form. */
class ContactMessage extends Model
{
    public const STATUSES = ['new' => 'New', 'in_review' => 'In review', 'replied' => 'Replied', 'closed' => 'Closed'];

    protected $fillable = ['name', 'email', 'subject', 'message', 'status', 'staff_notes', 'submission_key'];

    protected $hidden = ['submission_key', 'staff_notes'];

    protected $attributes = ['status' => 'new'];
}
