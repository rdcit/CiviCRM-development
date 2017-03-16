<?php

/**
 * This is the url the openclinica soap webservice can be accessed on.
 * The url has to end with a closing slash.
 *
 * Example: $ocWsInstanceURL = "http://127.0.0.1:8080/OpenClinica-ws/";
 */
$ocWsInstanceURL = "";


/**
 * An OpenClinica user that is authorised to use the webservices and
 * is added to the study the dataloader will target.
 *
 *  Example: $user = "ch686";
 */
$user = "";

/**
 * The password of the user in SHA-1 encrypted format. This information can
 * be looked up from the user_account table in the openclinica database.
 *
 * Example: $password = "21c19fdb4d6c652824b6c7b124db1c71a02577d0";
 *
 */
$password = "";

?>