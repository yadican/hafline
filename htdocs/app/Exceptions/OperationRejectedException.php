<?php

namespace App\Exceptions;

/**
 * A deliberate refusal whose message is safe to show to the user as is.
 */
class OperationRejectedException extends OperationException {}
