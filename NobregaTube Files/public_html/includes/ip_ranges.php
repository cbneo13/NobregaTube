<?php
function normalize_ip_address(string $input): ?string
{
	$packedAddress = inet_pton(trim($input));
	if ($packedAddress === false) {
		return null;
	}

	$normalizedAddress = inet_ntop($packedAddress);
	return $normalizedAddress === false ? null : $normalizedAddress;
}

function is_loopback_ip(string $input): bool
{
	$packedAddress = inet_pton(trim($input));
	if ($packedAddress === false) {
		return false;
	}

	if (strlen($packedAddress) === 4) {
		return ord($packedAddress[0]) === 127;
	}

	if ($packedAddress === str_repeat("\0", 15) . "\1") {
		return true;
	}

	return substr($packedAddress, 0, 12) === str_repeat("\0", 10) . "\xff\xff"
		&& ord($packedAddress[12]) === 127;
}

function normalize_ip_range(string $input): ?string
{
	$parts = explode('/', trim($input), 2);
	$address = inet_pton($parts[0]);
	if ($address === false) {
		return null;
	}

	$addressLength = strlen($address);
	$maxPrefix = $addressLength * 8;
	$prefix = $maxPrefix;
	if (isset($parts[1])) {
		if ($parts[1] === '' || !ctype_digit($parts[1])) {
			return null;
		}
		$prefix = (int)$parts[1];
		if ($prefix < 0 || $prefix > $maxPrefix) {
			return null;
		}
		if (($addressLength === 4 && $prefix < 16) || ($addressLength === 16 && $prefix < 32)) {
			return null;
		}
	}

	$remainingBits = $prefix;
	for ($index = 0; $index < $addressLength; $index++) {
		$bitsInByte = min(8, max(0, $remainingBits));
		$mask = $bitsInByte === 0 ? 0 : (0xFF << (8 - $bitsInByte)) & 0xFF;
		$address[$index] = chr(ord($address[$index]) & $mask);
		$remainingBits -= $bitsInByte;
	}

	$normalizedAddress = inet_ntop($address);
	if ($normalizedAddress === false) {
		return null;
	}

	return isset($parts[1]) ? $normalizedAddress . '/' . $prefix : $normalizedAddress;
}

function ip_matches_range(string $ip, string $range): bool
{
	$normalizedRange = normalize_ip_range($range);
	$packedIp = inet_pton($ip);
	if ($normalizedRange === null || $packedIp === false) {
		return false;
	}

	$parts = explode('/', $normalizedRange, 2);
	$packedNetwork = inet_pton($parts[0]);
	if ($packedNetwork === false || strlen($packedIp) !== strlen($packedNetwork)) {
		return false;
	}

	$prefix = isset($parts[1]) ? (int)$parts[1] : strlen($packedIp) * 8;
	$fullBytes = intdiv($prefix, 8);
	$remainingBits = $prefix % 8;
	if ($fullBytes > 0 && substr($packedIp, 0, $fullBytes) !== substr($packedNetwork, 0, $fullBytes)) {
		return false;
	}
	if ($remainingBits === 0) {
		return true;
	}

	$mask = (0xFF << (8 - $remainingBits)) & 0xFF;
	return (ord($packedIp[$fullBytes]) & $mask) === (ord($packedNetwork[$fullBytes]) & $mask);
}
