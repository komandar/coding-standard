<?php declare(strict_types = 1);

namespace SlevomatCodingStandard\Sniffs\TypeHints;

use SlevomatCodingStandard\Helpers\TokenHelper;

class DeclareStrictTypesSniff implements \PHP_CodeSniffer_Sniff
{

	const CODE_DECLARE_STRICT_TYPES_MISSING = 'DeclareStrictTypesMissing';

	const CODE_INCORRECT_STRICT_TYPES_FORMAT = 'IncorrectStrictTypesFormat';

	const CODE_INCORRECT_WHITESPACE_BETWEEN_OPEN_TAG_AND_DECLARE = 'IncorrectWhitespaceBetweenOpenTagAndDeclare';

	/** @var int */
	public $newlinesCountBetweenOpenTagAndDeclare = 0;

	/**
	 * @return int[]
	 */
	public function register(): array
	{
		return [
			T_OPEN_TAG,
		];
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param \PHP_CodeSniffer_File $phpcsFile
	 * @param int $openTagPointer
	 */
	public function process(\PHP_CodeSniffer_File $phpcsFile, $openTagPointer)
	{
		if ($phpcsFile->findPrevious(T_OPEN_TAG, $openTagPointer - 1) !== false) {
			return;
		}

		$tokens = $phpcsFile->getTokens();
		$declarePointer = TokenHelper::findNextEffective($phpcsFile, $openTagPointer + 1);

		if ($declarePointer === null) {
			$this->reportMissingDeclareStrict($phpcsFile, $openTagPointer);
			return;
		}

		if ($tokens[$declarePointer]['code'] !== T_DECLARE) {
			$this->reportMissingDeclareStrict($phpcsFile, $openTagPointer);
			return;
		}

		$strictTypesPointer = null;
		for ($i = $tokens[$declarePointer]['parenthesis_opener'] + 1; $i < $tokens[$declarePointer]['parenthesis_closer']; $i++) {
			if ($tokens[$i]['code'] === T_STRING && $tokens[$i]['content'] === 'strict_types') {
				$strictTypesPointer = $i;
				break;
			}
		}

		if ($strictTypesPointer === null) {
			$this->reportMissingDeclareStrict($phpcsFile, $declarePointer);
			return;
		}

		$numberPointer = $phpcsFile->findNext(T_LNUMBER, $strictTypesPointer + 1);
		if ($tokens[$numberPointer]['content'] !== '1') {
			$this->reportMissingDeclareStrict($phpcsFile, $declarePointer);
			return;
		}

		$strictTypesContent = TokenHelper::getContent($phpcsFile, $strictTypesPointer, $numberPointer);
		if ($strictTypesContent !== 'strict_types = 1') {
			$phpcsFile->addError(
				sprintf(
					'Expected strict_types = 1, found %s.',
					$strictTypesContent
				),
				$strictTypesPointer,
				self::CODE_INCORRECT_STRICT_TYPES_FORMAT
			);
		}

		$openingWhitespace = substr($tokens[$openTagPointer]['content'], strlen('<?php'));
		$newlinesCountBetweenOpenTagAndDeclare = (int) trim((string) $this->newlinesCountBetweenOpenTagAndDeclare);
		if ($newlinesCountBetweenOpenTagAndDeclare === 0) {
			if ($openingWhitespace !== ' ') {
				$phpcsFile->addError(
					'There must be a single space between the PHP open tag and declare statement.',
					$declarePointer,
					self::CODE_INCORRECT_WHITESPACE_BETWEEN_OPEN_TAG_AND_DECLARE
				);
			}
		} else {
			$startToken = $openTagPointer + 1;
			do {
				$possibleWhitespacePointer = TokenHelper::findNextAnyToken($phpcsFile, $startToken);
				if ($possibleWhitespacePointer !== null && $tokens[$possibleWhitespacePointer]['code'] === T_WHITESPACE) {
					$openingWhitespace .= $tokens[$possibleWhitespacePointer]['content'];
				}
				$startToken++;
			} while ($possibleWhitespacePointer !== null && $tokens[$possibleWhitespacePointer]['code'] === T_WHITESPACE);
			$newlinesCount = substr_count($openingWhitespace, $phpcsFile->eolChar);
			if ($newlinesCount !== $newlinesCountBetweenOpenTagAndDeclare) {
				$phpcsFile->addError(
					sprintf(
						'Expected %d newlines between PHP open tag and declare statement, found %d.',
						$newlinesCountBetweenOpenTagAndDeclare,
						$newlinesCount
					),
					$declarePointer,
					self::CODE_INCORRECT_WHITESPACE_BETWEEN_OPEN_TAG_AND_DECLARE
				);
			}
		}
	}

	private function reportMissingDeclareStrict(\PHP_CodeSniffer_File $phpcsFile, int $openTagPointer)
	{
		$phpcsFile->addError(
			'Missing declare(strict_types = 1).',
			$openTagPointer,
			self::CODE_DECLARE_STRICT_TYPES_MISSING
		);
	}

}