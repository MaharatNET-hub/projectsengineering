<?php

return [
    'nav' => 'Request a study review',
    'index' => [
        'title' => 'Request a study review',
        'sub' => 'Pick the type of study. You fill in a short form with the values we check and attach the supporting file — you get the automated check right away and our engineer\'s review by email.',
        'why_title' => 'Why a form and not just a file?',
        'why' => ['Every value the check needs is asked for — nothing is missed in a long PDF', 'Instant feedback: missing or out-of-range values are flagged before you send', 'The supporting file is still attached, and compared with what you enter', 'The engineer reviews, edits and signs the final report'],
        'start' => 'Start',
        'fields' => ':n values',
        'checks' => ':n checks',
    ],
    'form' => [
        'you' => 'Your details', 'name' => 'Name', 'company' => 'Company', 'email' => 'Email', 'phone' => 'Phone',
        'project' => 'Project name', 'reference' => 'Submittal / document no.', 'notes' => 'Notes for the engineer',
        'file' => 'Supporting file', 'drop' => 'Drop the file here or browse', 'file_hint' => 'PDF, Excel, Word, image, DWG or ZIP · up to :mb MB',
        'prefill' => 'Fill the table from the file', 'prefill_hint' => 'Reads the panel data sheets in the PDF and fills the rows below — check every value.',
        'prefill_reading' => 'Reading the file', 'prefill_done' => ':n rows filled from the file — please check them.', 'prefill_none' => 'No panel data could be read from this file — please fill the table by hand.',
        'remove_row' => 'Remove', 'row' => 'Row', 'send' => 'Send the study', 'uploading' => 'Uploading the file', 'checking' => 'Checking the file', 'analysing' => 'Analysing',
        'required_note' => 'Fields marked * are required.', 'computed' => 'Calculated',
        'fix' => 'Please correct the highlighted fields.', 'yes' => 'Yes', 'no' => 'No', 'choose' => 'Choose…',
        'back' => 'All study types',
    ],
    'result' => [
        'title' => 'Study :code', 'received' => 'Your study was received', 'code' => 'Tracking code', 'keep' => 'Keep this code — this page is where the review will appear.',
        'preliminary' => 'Automated check (preliminary)', 'preliminary_note' => 'This is the system\'s automatic check of the values you entered. Our engineer reviews it and may add, edit or remove comments before issuing the final report.',
        'final' => 'Engineer\'s review', 'decision' => 'Action', 'suggested' => 'Suggested action', 'findings' => 'Findings', 'checks' => 'Checks applied', 'values' => 'Values entered',
        'none' => 'No findings — every check that could be applied complies.', 'waiting' => 'Our engineer is reviewing your study. You will receive the report by email.',
        'download' => 'Download the report (PDF)', 'print' => 'Print / save as PDF', 'status' => 'Status', 'submitted' => 'Submitted', 'file' => 'Supporting file',
        'cross' => 'Form compared with the file', 'cross_ok' => ':matched of :total panels found in the file were matched with the form.', 'cross_none' => 'The file could not be read for comparison (layout not recognised) — the engineer will check it by hand.',
        'remarks' => 'Engineer\'s remarks', 'engineer' => 'Reviewed by', 'another' => 'Request another study',
    ],
    'status' => ['submitted' => 'Received', 'review' => 'Under engineer review', 'issued' => 'Review issued', 'archived' => 'Closed'],
    'decision' => ['approved' => 'Approved', 'noted' => 'Approved as noted', 'revise' => 'Revise and resubmit', 'rejected' => 'Rejected'],
    'finding' => ['fail' => 'Non-compliant', 'warn' => 'Clarify', 'missing' => 'Missing data', 'mismatch' => 'Form ≠ file', 'pass' => 'Complies', 'na' => 'Not applicable'],
    'err' => [
        'required' => 'Required.', 'number' => 'Enter a number.', 'min' => 'Must be at least :min.', 'max' => 'Must be at most :max.', 'choose' => 'Choose one of the options.',
        'long' => 'Too long (max :max characters).', 'rows_min' => 'Add at least :n row(s).', 'rows_max' => 'At most :n rows.', 'duplicate' => 'Used twice — each row needs its own name.',
        'file' => 'Attach the supporting file.', 'file_type' => 'This file type is not accepted.', 'file_size' => 'The file is larger than :mb MB.',
    ],
    'mail' => [
        'received_subject' => 'Study received', 'issued_subject' => 'Study review issued', 'new_subject' => 'New study',
        'hello' => 'Hello :name,', 'received' => 'We received your study ":type" for :project. The automated check is ready on the tracking page; our engineer will review it and send you the final report.',
        'issued' => 'The review of your study ":type" for :project has been issued. Action: :decision.', 'open' => 'Open the review',
    ],
];
