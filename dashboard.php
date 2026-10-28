<?php
// Рөлге қарай тиісті кабинетке бағыттайды
require 'session.php';
require_login();
go(role() . '/dashboard.php');
