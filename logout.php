<?php require __DIR__.'/includes/bootstrap.php'; log_event('logout'); session_destroy(); session_start(); flash('You are logged out.'); redirect('/');
