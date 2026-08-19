<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only duplicate title groups. Never rewrites titles. Never publishes.
 */
class Luxe_Score_Repair_Duplicates {

	/**
	 * Exact published title collisions for human Rank Math review.
	 *
	 * @return array<int,array{title:string,count:int,ids:int[]}>
	 */
	public static function groups() {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->posts ) ) {
			return array();
		}
		$sql = "SELECT post_title AS title, COUNT(*) AS n, GROUP_CONCAT(ID ORDER BY ID ASC) AS ids
			FROM {$wpdb->posts}
			WHERE post_status = 'publish'
			AND post_type IN ('post','page','product')
			AND post_title <> ''
			GROUP BY post_title
			HAVING n > 1
			ORDER BY n DESC, title ASC
			LIMIT 20";
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$title = isset( $row['title'] ) ? (string) $row['title'] : '';
			$count = isset( $row['n'] ) ? (int) $row['n'] : 0;
			$ids   = array();
			if ( ! empty( $row['ids'] ) ) {
				foreach ( explode( ',', (string) $row['ids'] ) as $id ) {
					$id = (int) $id;
					if ( $id > 0 ) {
						$ids[] = $id;
					}
				}
			}
			if ( '' === $title || $count < 2 ) {
				continue;
			}
			$out[] = array(
				'title' => $title,
				'count' => $count,
				'ids'   => $ids,
			);
		}
		return $out;
	}
}
