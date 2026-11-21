<?php
// Keep page scripts from reading the session cookie.
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start(['cookie_httponly' => true]);
}
