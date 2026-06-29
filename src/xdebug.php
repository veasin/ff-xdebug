<?php
declare(strict_types=1);
namespace ff;
function xdebug(null|bool|array $enable = null): mixed{
	static $traceFile = null;
	static $stopped = false;
	static $cached = null;
	if($enable === null || $enable === true){
		$stopped = false;
		$cached = null;
		@\xdebug_stop_trace();
		\ini_set('xdebug.trace_format', 1);
		$traceFile = \xdebug_start_trace(getcwd() . '/xdebug');
		return null;
	}
	if(!$stopped){
		@\xdebug_stop_trace();
		$stopped = true;
	}
	if($cached !== null) return $enable === false ? $cached : null;
	$filterNames = is_array($enable) ? $enable : [];
	$showDetails = false;
	if($filterNames){
		$cleaned = [];
		foreach($filterNames as $name){
			if($name === 'ff+') $showDetails = true;
			else $cleaned[] = $name;
		}
		$filterNames = $cleaned;
	}
	if(!$traceFile || !file_exists($traceFile)){
		$alt = \xdebug_get_tracefile_name();
		if($alt && file_exists($alt)) $traceFile = $alt;
	}
	if(!$traceFile || !file_exists($traceFile)) return $enable === false ? [] : null;
	$lines = file($traceFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$entries = [];
	$calls = [];
	$firstTime = null;
	$lastTime = null;
	foreach($lines as $line){
		if($line === '' || $line[0] === 'V' || $line[0] === 'F' || str_starts_with($line, 'TRACE ')) continue;
		$fields = explode("\t", $line);
		if(count($fields) < 5) continue;
		$flag = $fields[2] ?? '';
		if($flag !== '0' && $flag !== '1') continue;
		$flag = (int)$flag;
		$time = (float)$fields[3];
		if($flag === 0){
			$funcName = $fields[5] ?? '';
			$isUser = (int)($fields[6] ?? 0);
			if(str_starts_with($funcName, 'xdebug_')) continue;
			if($funcName === 'ff\\debug') continue;
			if($firstTime === null) $firstTime = $time;
			$funcNum = (int)$fields[1];
			$level = (int)$fields[0];
			$file = $fields[8] ?? '';
			$lineNum = $fields[9] ?? '';
			$argCount = (int)($fields[10] ?? 0);
			$args = $argCount > 0 ? implode(', ', array_slice($fields, 11)) : '';
			$args = preg_replace('/\?\?\?/', '', $args);
			if(preg_match_all('/class Closure\s*\{/', $args, $m, PREG_OFFSET_CAPTURE)){
				for($i = count($m[0]) - 1; $i >= 0; $i--){
					$start = $m[0][$i][1];
					$depth = 0;
					$end = $start;
					for($j = $start; $j < strlen($args); $j++){
						$args[$j] === '{' && $depth++;
						if($args[$j] === '}' && --$depth === 0){
							$end = $j;
							break;
						}
					}
					$block = substr($args, $start, $end - $start + 1);
					$name = '';
					if(preg_match("/public\\s+\\\$name\\s*=\\s*'([^']+)'/", $block, $n)) $name = $n[1];
					if($name !== '') $args = substr_replace($args, $name, $start, $end - $start + 1);
				}
			}
			$args = str_replace('\\\\', '\\', $args);
			$args = preg_replace(['/, \s*,/', '/^, /', '/, $/'], ',', $args);
			$args = trim($args, ' ,');
			$entries[$funcNum] = [
				'level' => $level,
				'func_name' => $funcName,
				'display_name' => str_starts_with($funcName, 'ff\\') ? substr($funcName, 3) : $funcName,
				'file' => $file,
				'line' => $lineNum,
				'args' => $args,
				'time' => $time,
				'is_user' => $isUser,
			];
		}
		else{
			$funcNum = (int)$fields[1];
			if(isset($entries[$funcNum])){
				$entry = $entries[$funcNum];
				$duration = round(($time - $entry['time']) * 1000, 3);
				$elapsed = round(($entry['time'] - $firstTime) * 1000, 3);
				$calls[] = [
					'level' => $entry['level'],
					'func_name' => $entry['func_name'],
					'display_name' => $entry['display_name'],
					'file' => $entry['file'],
					'line' => $entry['line'],
					'args' => $entry['args'],
					'elapsed' => $elapsed,
					'duration' => $duration,
					'is_user' => $entry['is_user'],
				];
				unset($entries[$funcNum]);
				if($time > $lastTime) $lastTime = $time;
			}
		}
	}
	$cached = $calls;
	$cascade = function(array $arr, array $remove): array{
		foreach($remove as $i => $level){
			$j = $i - 1;
			while($j >= 0){
				if($arr[$j]['level'] <= $level) break;
				$ce = $arr[$j];
				if(str_starts_with($ce['func_name'], '{closure') && str_replace('\\', '/', substr($ce['func_name'], 9, strrpos($ce['func_name'], ':') - 9)) !== str_replace('\\', '/', $ce['file'])){
					$skipLevel = $ce['level'];
					$j--;
					while($j >= 0 && $arr[$j]['level'] > $skipLevel) $j--;
					continue;
				}
				$remove[$j] = true;
				$j--;
			}
		}
		return array_values(array_filter($arr, fn($k) => !isset($remove[$k]), ARRAY_FILTER_USE_KEY));
	};
	if($filterNames){
		$remove = [];
		foreach($cached as $i => $c){
			foreach($filterNames as $name){
				$matched = false;
				if($name === '\\'){
					if(!$c['is_user']) $matched = true;
				}
				elseif($name === '{}'){
					if(str_starts_with($c['func_name'], '{closure')) $matched = true;
				}
				elseif(str_starts_with($name, '!')){
					if(str_starts_with($c['func_name'], substr($name, 1))) $matched = true;
				}
				else{
					if($c['display_name'] === $name) $matched = true;
				}
				if($matched){
					$remove[$i] = $c['level'];
					break;
				}
			}
		}
		$cached = $cascade($cached, $remove);
	}
	@\unlink($traceFile);
	if($enable === false) return $cached;
	$srcDir = str_replace('\\', '/', __DIR__) . '/';
	$removeClo = [];
	foreach($cached as $i => $c){
		if(str_starts_with($c['func_name'], '{closure')){
			$normalize = fn($p) => str_replace('\\', '/', $p);
			if($normalize(substr($c['func_name'], 9, strrpos($c['func_name'], ':') - 9)) === $normalize($c['file']) && str_starts_with($normalize($c['file']), $srcDir)) $removeClo[$i] = $c['level'];
		}
	}
	if($removeClo) $cached = $cascade($cached, $removeClo);
	if(!$showDetails){
		$foldParents = [];
		foreach($cached as $i => $c){
			if(str_starts_with($c['func_name'], 'ff\\')) $foldParents[$i] = $c['level'];
		}
		if($foldParents){
			$removeFold = [];
			foreach($foldParents as $i => $level){
				$j = $i - 1;
				while($j >= 0){
					if($cached[$j]['level'] <= $level) break;
					$ce = $cached[$j];
					if(str_starts_with($ce['func_name'], '{closure') && str_replace('\\', '/', substr($ce['func_name'], 9, strrpos($ce['func_name'], ':') - 9)) !== str_replace('\\', '/', $ce['file'])){
						$skipLevel = $ce['level'];
						$j--;
						while($j >= 0 && $cached[$j]['level'] > $skipLevel) $j--;
						continue;
					}
					$removeFold[$j] = true;
					$j--;
				}
			}
			$calls = array_values(array_filter($cached, fn($k) => !isset($removeFold[$k]), ARRAY_FILTER_USE_KEY));
		}
		else $calls = $cached;
	}
	else $calls = $cached;
	\usort($calls, fn($a, $b) => $a['elapsed'] <=> $b['elapsed']);
	$totalMs = $firstTime !== null && $lastTime !== null ? round(($lastTime - $firstTime) * 1000, 3) : 0.0;
	// ... display width calculations ...
	$maxCall = $maxElapsed = $maxDuration = $maxCaller = 0;
	foreach($calls as $c){
		$indent = $c['level'] > 2 ? str_repeat('.', min(($c['level'] - 2) * 2, 10)) . ' ' : '';
		$callStr = $indent . $c['display_name'] . '(' . $c['args'] . ')';
		$l = strlen($callStr);
		if($l > $maxCall) $maxCall = $l;
		$l = strlen(number_format($c['elapsed'], 3));
		if($l > $maxElapsed) $maxElapsed = $l;
		$l = strlen(number_format($c['duration'], 3));
		if($l > $maxDuration) $maxDuration = $l;
		$callerStr = ($c['file'] && $c['file'] !== '') ? $c['file'] . ':' . $c['line'] : '';
		$l = strlen($callerStr);
		if($l > $maxCaller) $maxCaller = $l;
	}
	$wCall = max($maxCall + 1, 12);
	$wMsBracket = $maxDuration > 0 ? $maxDuration + 2 : 0;
	$wMerged = $maxElapsed + $wMsBracket;
	$wCaller = max($maxCaller, 6);
	$totalW = 2 + $wMerged + 1 + $wCall + 1 + $wCaller;
	echo "\n  Xdebug Trace\n";
	echo str_repeat('━', $totalW) . "\n";
	$hdrPad = $wMsBracket > 0 ? 1 : 0;
	echo sprintf("  %*s%-*s %-*s %-*s\n", $maxElapsed + $hdrPad, '+ms', $wMsBracket - $hdrPad, '', $wCall, 'call', $wCaller, 'caller');
	echo str_repeat('─', $totalW) . "\n";
	foreach($calls as $c){
		$indent = $c['level'] > 2 ? str_repeat('.', min(($c['level'] - 2) * 2, 10)) . ' ' : '';
		$elapsedStr = number_format($c['elapsed'], 3);
		$durationStr = $c['duration'] !== 0.0 ? '[' . number_format($c['duration'], 3) . ']' : '';
		$callStr = $indent . $c['display_name'] . '(' . $c['args'] . ')';
		$callerStr = ($c['file'] && $c['file'] !== '') ? $c['file'] . ':' . $c['line'] : '';
		echo sprintf("  %*s%-*s %-*s %-*s\n", $maxElapsed, $elapsedStr, $wMsBracket, $durationStr, $wCall, $callStr, $wCaller, $callerStr);
	}
	echo str_repeat('─', $totalW) . "\n";
	$totalMsStr = $totalMs > 0 ? ', ' . number_format($totalMs, 3) . ' ms' : '';
	echo sprintf("  Total: %d calls%s\n", count($calls), $totalMsStr);
	echo "\n";
	return null;
}
