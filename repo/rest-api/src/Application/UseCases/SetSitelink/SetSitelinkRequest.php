<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\UseCases\SetSitelink;

/**
 * @license GPL-2.0-or-later
 */
class SetSitelinkRequest {

	private string $itemId;
	private string $siteId;
	private array $sitelink;
	private array $editTags;
	private bool $isBot;
	private ?string $comment;

	public function __construct(
		string $itemId,
		string $siteId,
		array $sitelink,
		array $editTags,
		bool $isBot,
		?string $comment
	) {
		$this->itemId = $itemId;
		$this->siteId = $siteId;
		$this->sitelink = $sitelink;
		$this->editTags = $editTags;
		$this->isBot = $isBot;
		$this->comment = $comment;
	}

	public function getItemId(): string {
		return $this->itemId;
	}

	public function getSiteId(): string {
		return $this->siteId;
	}

	public function getSitelink(): array {
		return $this->sitelink;
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

}
