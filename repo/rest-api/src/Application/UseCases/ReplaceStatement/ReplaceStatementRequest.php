<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\UseCases\ReplaceStatement;

/**
 * @license GPL-2.0-or-later
 */
class ReplaceStatementRequest {

	private ?string $subjectId;
	private string $statementId;
	private array $statement;
	private array $editTags;
	private bool $isBot;
	private ?string $comment;
	private ?string $username;

	public function __construct(
		string $statementId,
		array $statement,
		array $editTags,
		bool $isBot,
		?string $comment,
		?string $username,
		string $subjectId = null
	) {
		$this->statementId = $statementId;
		$this->statement = $statement;
		$this->editTags = $editTags;
		$this->isBot = $isBot;
		$this->comment = $comment;
		$this->username = $username;
		$this->subjectId = $subjectId;
	}

	public function getStatementId(): string {
		return $this->statementId;
	}

	public function getStatement(): array {
		return $this->statement;
	}

	public function getEditTags(): array {
		return $this->editTags;
	}

	public function isBot(): bool {
		return $this->isBot;
	}

	public function getComment(): ?string {
		return $this->comment;
	}

	public function hasUser(): bool {
		return $this->username !== null;
	}

	public function getUsername(): ?string {
		return $this->username;
	}

	public function getSubjectId(): ?string {
		return $this->subjectId;
	}
}
