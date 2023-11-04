<?php

function encrypt_password($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function is_valid_password($encryptedPassword, $storedPassword): bool
{
    return password_verify($encryptedPassword, $storedPassword);
}
