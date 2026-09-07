<?php

declare(strict_types=1);

// config for Happenv/AccessControl
return [

    /*
     * Whether a refusal names the permission that was missing.
     *
     * OFF by default, because the message can reach a caller outside the organisation and a
     * permission catalogue is a description of the application's internals. ON is the right choice
     * wherever the refusal is read by somebody who is meant to ask for the grant: it turns
     * "Unauthorized." — which is also what a MISSPELLED ability produces, indistinguishably — into
     * a sentence naming what to fix.
     */
    'display_permission_in_exception' => false,

];
