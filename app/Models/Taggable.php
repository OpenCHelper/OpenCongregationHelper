<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Taggable model for polymorphic tagging.
 *
 * This model is used to represent the polymorphic relationship between tags and other models.
 *
 * It is NOT meant to be used directly, but only by the Tag model to allow for more dynamic
 * queries.
 */
class Taggable extends Model {}
