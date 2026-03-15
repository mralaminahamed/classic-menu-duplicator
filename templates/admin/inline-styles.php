<?php
/**
 * Inline styles template for nav-menus.php admin page.
 *
 * @package ClassicMenuDuplicator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<style id="cmd-inline-styles">

	/* ── Toolbar button spacing ─────────────────────────────────── */
	#cmd-duplicate-menu,
	#cmd-snapshot-toggle,
	#cmd-export-menu {
		vertical-align: middle;
		margin-left: 6px;
	}

	/* ── Toast notice ───────────────────────────────────────────── */
	.cmd-toast {
		margin: 8px 0 0;
	}

	/* ── Name modal overlay ─────────────────────────────────────── */
	#cmd-modal-overlay {
		display: none;
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, .55);
		z-index: 100100;
		align-items: center;
		justify-content: center;
	}

	#cmd-modal-overlay[style*="display: block"],
	#cmd-modal-overlay[style*="display:block"] {
		display: flex !important;
	}

	#cmd-modal {
		background: #fff;
		border-radius: 4px;
		box-shadow: 0 4px 24px rgba(0,0,0,.25);
		padding: 24px 28px;
		width: 100%;
		max-width: 420px;
	}

	#cmd-modal h2 {
		margin: 0 0 16px;
		font-size: 16px;
		font-weight: 600;
		color: #1d2327;
	}

	#cmd-modal label {
		display: block;
		margin-bottom: 6px;
		font-weight: 500;
		color: #1d2327;
	}

	#cmd-modal-name {
		width: 100%;
		box-sizing: border-box;
		margin-bottom: 16px;
	}

	.cmd-modal-actions {
		display: flex;
		gap: 8px;
	}

	/* ── Snapshot panel ─────────────────────────────────────────── */
	#cmd-snapshot-panel {
		position: fixed;
		top: 0;
		right: -320px;
		width: 300px;
		height: 100vh;
		background: #fff;
		border-left: 1px solid #c3c4c7;
		box-shadow: -2px 0 8px rgba(0,0,0,.1);
		z-index: 99990;
		display: flex;
		flex-direction: column;
		transition: right .25s ease;
		overflow: hidden;
	}

	#cmd-snapshot-panel.is-visible {
		right: 0;
	}

	#cmd-snapshot-panel-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 12px 16px;
		border-bottom: 1px solid #c3c4c7;
		font-weight: 600;
		font-size: 13px;
		color: #1d2327;
		background: #f6f7f7;
	}

	#cmd-snapshot-close {
		font-size: 20px;
		line-height: 1;
		color: #787c82;
		padding: 0 4px;
		cursor: pointer;
	}

	#cmd-snapshot-close:hover {
		color: #1d2327;
	}

	#cmd-snapshot-save-row {
		padding: 12px 16px;
		border-bottom: 1px solid #f0f0f1;
	}

	#cmd-save-snapshot {
		width: 100%;
	}

	#cmd-snapshot-list {
		flex: 1;
		overflow-y: auto;
		margin: 0;
		padding: 0;
		list-style: none;
	}

	.cmd-snapshot-empty {
		padding: 16px;
		color: #787c82;
		font-size: 12px;
		font-style: italic;
	}

	.cmd-snapshot-item {
		display: flex;
		flex-direction: column;
		padding: 10px 16px;
		border-bottom: 1px solid #f0f0f1;
		position: relative;
	}

	.cmd-snapshot-item:hover {
		background: #f6f7f7;
	}

	.cmd-snapshot-label {
		font-size: 12px;
		font-weight: 500;
		color: #1d2327;
		padding-right: 24px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.cmd-snapshot-date {
		font-size: 11px;
		color: #787c82;
		margin-top: 2px;
	}

	.cmd-snapshot-delete {
		position: absolute;
		top: 10px;
		right: 12px;
		font-size: 16px;
		line-height: 1;
		color: #b32d2e;
		cursor: pointer;
		opacity: .6;
	}

	.cmd-snapshot-delete:hover {
		opacity: 1;
	}

	/* ── Duplicate item link ─────────────────────────────────────── */
	.cmd-duplicate-item {
		margin-left: 8px;
		color: #2271b1;
	}

	.cmd-duplicate-item:hover {
		color: #135e96;
	}

</style>
