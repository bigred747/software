<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One signal per safety lock and one signal per named plugin.
 */
class LUOR_Board {

	/**
	 * @param array<string,mixed> $report Report.
	 * @return array<int,array{id:string,group:string,label:string,level:string,detail:string}>
	 */
	public static function build( $report ) {
		$rows = self::safety();
		$present = isset( $report['present'] ) && is_array( $report['present'] ) ? $report['present'] : array();
		$deleted = isset( $report['deleted'] ) && is_array( $report['deleted'] ) ? $report['deleted'] : array();
		$failed  = isset( $report['failed'] ) && is_array( $report['failed'] ) ? $report['failed'] : array();
		$scanned = ! empty( $report['scanned'] );
		$labels  = LUOR_Targets::labels();
		foreach ( LUOR_Targets::folders() as $folder ) {
			$key   = LUOR_Targets::normalize( $folder );
			$label = isset( $labels[ $folder ] ) ? $labels[ $folder ] : $folder;
			if ( isset( $failed[ $key ] ) ) {
				$rows[] = self::row( 'target_' . $key, 'Plugins', $label, 'red', (string) $failed[ $key ] );
				continue;
			}
			if ( isset( $present[ $key ] ) ) {
				$rows[] = self::row( 'target_' . $key, 'Plugins', $label, 'yellow', 'Still installed. It is deleted only after you confirm the Snapshot backup.' );
				continue;
			}
			if ( isset( $deleted[ $key ] ) ) {
				$rows[] = self::row( 'target_' . $key, 'Plugins', $label, 'green', 'Deactivated and deleted.' );
				continue;
			}
			if ( $scanned ) {
				$rows[] = self::row( 'target_' . $key, 'Plugins', $label, 'green', 'Not installed.' );
				continue;
			}
			$rows[] = self::row( 'target_' . $key, 'Plugins', $label, 'yellow', 'Not scanned yet.' );
		}
		return $rows;
	}

	/**
	 * @return array<int,array{id:string,group:string,label:string,level:string,detail:string}>
	 */
	public static function safety() {
		return array(
			self::row( 'backup_gate', 'Safety', 'Backup gate', 'green', 'Delete stays off until the Snapshot backup box is checked.' ),
			self::row( 'exact_folder', 'Safety', 'Exact folder lock', 'green', 'Only the 18 named folders can match. A lookalike name is left alone.' ),
			self::row( 'protected_stack', 'Safety', 'Protected stack', 'green', 'WooCommerce, WZone, Rank Math, LiteSpeed, Wordfence, Amazon Bridge, Product Scout, Ultimate Authority, and the homepage keeper cannot match.' ),
			self::row( 'self_lock', 'Safety', 'Self lock', 'green', 'This remover cannot delete itself.' ),
			self::row( 'no_auto_delete', 'Safety', 'No delete on activate', 'green', 'Activation only stores the signal board. Nothing is deactivated.' ),
			self::row( 'deactivate_first', 'Safety', 'Deactivate first', 'green', 'Each match is deactivated before its folder is removed.' ),
			self::row( 'path_jail', 'Safety', 'Plugins directory jail', 'green', 'A folder is removed only when its real path stays inside the WordPress plugins directory.' ),
			self::row( 'public_lock', 'Safety', 'Public site lock', 'green', 'This plugin does not hook the public site, content, cron, or Amazon URLs.' ),
		);
	}

	/**
	 * @param array<int,array{level:string}> $signals Signals.
	 * @return array{green:int,yellow:int,red:int,total:int}
	 */
	public static function tally( $signals ) {
		$tally = array(
			'green'  => 0,
			'yellow' => 0,
			'red'    => 0,
			'total'  => 0,
		);
		foreach ( $signals as $signal ) {
			$level = isset( $signal['level'] ) ? $signal['level'] : 'red';
			if ( ! isset( $tally[ $level ] ) ) {
				$level = 'red';
			}
			$tally[ $level ]++;
			$tally['total']++;
		}
		return $tally;
	}

	/**
	 * @param string $id ID.
	 * @param string $group Group.
	 * @param string $label Label.
	 * @param string $level Level.
	 * @param string $detail Detail.
	 * @return array{id:string,group:string,label:string,level:string,detail:string}
	 */
	private static function row( $id, $group, $label, $level, $detail ) {
		return array(
			'id'     => $id,
			'group'  => $group,
			'label'  => $label,
			'level'  => $level,
			'detail' => $detail,
		);
	}
}
