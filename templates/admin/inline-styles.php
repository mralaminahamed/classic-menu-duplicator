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
<style id="cmdu-inline-styles">

	/* ── Toolbar button spacing ─────────────────────────────────── */
	#cmdu-duplicate-menu,
	#cmdu-snapshot-toggle,
	#cmdu-export-menu {
		vertical-align: middle;
		margin-left: 6px;
	}

	/* ── Toast notice ───────────────────────────────────────────── */
	.cmdu-toast {
		margin: 8px 0 0;
	}

	/* ── Name modal overlay ─────────────────────────────────────── */
	#cmdu-modal-overlay {
		display: none;
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, .55);
		z-index: 100100;
		align-items: center;
		justify-content: center;
	}

	#cmdu-modal-overlay[style*="display: block"],
	#cmdu-modal-overlay[style*="display:block"] {
		display: flex !important;
	}

	#cmdu-modal {
		background: #fff;
		border-radius: 4px;
		box-shadow: 0 4px 24px rgba(0,0,0,.25);
		padding: 24px 28px;
		width: 100%;
		max-width: 420px;
	}

	#cmdu-modal h2 {
		margin: 0 0 16px;
		font-size: 16px;
		font-weight: 600;
		color: #1d2327;
	}

	#cmdu-modal label {
		display: block;
		margin-bottom: 6px;
		font-weight: 500;
		color: #1d2327;
	}

	#cmdu-modal-name {
		width: 100%;
		box-sizing: border-box;
		margin-bottom: 16px;
	}

	.cmdu-modal-actions {
		display: flex;
		gap: 8px;
	}

	/* ── Snapshot panel ─────────────────────────────────────────── */
	#cmdu-snapshot-panel {
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

	#cmdu-snapshot-panel.is-visible {
		right: 0;
	}

	#cmdu-snapshot-panel-header {
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

	#cmdu-snapshot-close {
		font-size: 20px;
		line-height: 1;
		color: #787c82;
		padding: 0 4px;
		cursor: pointer;
	}

	#cmdu-snapshot-close:hover {
		color: #1d2327;
	}

	#cmdu-snapshot-save-row {
		padding: 12px 16px;
		border-bottom: 1px solid #f0f0f1;
	}

	#cmdu-save-snapshot {
		width: 100%;
	}

	#cmdu-snapshot-list {
		flex: 1;
		overflow-y: auto;
		margin: 0;
		padding: 0;
		list-style: none;
	}

	.cmdu-snapshot-empty {
		padding: 16px;
		color: #787c82;
		font-size: 12px;
		font-style: italic;
	}

	.cmdu-snapshot-item {
		display: flex;
		flex-direction: column;
		padding: 10px 16px;
		border-bottom: 1px solid #f0f0f1;
		position: relative;
	}

	.cmdu-snapshot-item:hover {
		background: #f6f7f7;
	}

	.cmdu-snapshot-label {
		font-size: 12px;
		font-weight: 500;
		color: #1d2327;
		padding-right: 24px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.cmdu-snapshot-date {
		font-size: 11px;
		color: #787c82;
		margin-top: 2px;
	}

	.cmdu-snapshot-delete {
		position: absolute;
		top: 10px;
		right: 12px;
		font-size: 16px;
		line-height: 1;
		color: #b32d2e;
		cursor: pointer;
		opacity: .6;
	}

	.cmdu-snapshot-delete:hover {
		opacity: 1;
	}

	/* ── Duplicate item link ─────────────────────────────────────── */
	.cmdu-duplicate-item {
		margin-left: 8px;
		color: #2271b1;
	}

	.cmdu-duplicate-item:hover {
		color: #135e96;
	}

</style>
