<?php
/*
 * !
 * Aron Heinecke
 * http://proctet.net
 * support@proctet.net
 * 2016
 */
class dbException extends Exception {
	public function __construct($message, $code = 0, Exception $previous = null) {
		parent::__construct ( $message, $code, $previous );
	}
	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
	public function customFunction() {
		echo "A custom function for this type of exception\n";
	}
}
class clanDB extends dbException {
	private $db;
	public function __construct() {
		require 'includes/config.clantool.db.inc.php';
		$_access = getTS3Conf();
		date_default_timezone_set('Europe/Berlin');
		$this->db = new mysqli ( $_access ["host"], $_access ["user"], $_access ["pass"], $_access ["db"] );
	}
	private function escapeData(&$data) {
		$data = $this->db->real_escape_string ( $data );
	}
	
	/**
	 * Get difference table
	 * @param string $date1
	 * @param string $date2
	 * @throws dbException
	 */
	public function getDifference($date1, $date2) {
		$this->escapeData($date1);
		$this->escapeData($date2);
		if ($query = $this->db->prepare ( 'select names.name, m1.id, m2.date as `Date1`, m1.date as  `Date2`, m2.exp as `Exp1`,
		m1.exp as `Exp2`, m2.cp as `CP1`, m1.cp as `CP2`, 
		(CAST(m1.exp as  signed)-CAST(m2.exp as signed)) AS `EXP-Done`, 
		(`m1`.`cp`-`m2`.`cp`) AS `CP-Done`, DATEDIFF(m1.date,m2.date) AS `days` 
		from member as m2 right 
		join member as m1 on m1.id = m2.id 
		and m1.date LIKE "'.$date2.'%"
		JOIN (SELECT n1.id,n1.`name` FROM 
				(SELECT id,MAX(updated) as maxdate 
				FROM member_names 
				GROUP BY id) as nEndDate 
				JOIN member_names AS n1 ON ( 
					n1.id = nEndDate.id 
					AND n1.updated = nEndDate.maxdate 
				) 
		) names 
		on m2.id = names.id 
		where m2.date LIKE "'.$date1.'%" OR ( m2.date >  "'.$date1.'%" AND m2.date NOT LIKE "'.$date2.'%" ) 
		group by id order by `CP-Done`, `EXP-Done`' )) { // Y-m-d G:i:s Y-m-d h:i:s
			$query->execute ();
			$result = $query->get_result ();
			
			if (! $result) {
				throw new dbException ( $this->db->error, 500 );
			}
			
			if ($result->num_rows == 0) {
				$resultset = null;
			} else {
				$resultset = array ();
				while ( $row = $result->fetch_assoc () ) {
					$resultset [] = array(
								'name' => $row['name'],
								'date1' => $row['Date1'],
								'date2' => $row['Date2'],
								'exp1' => $row['Exp1'],
								'exp2' => $row['Exp2'],
								'cp1' => $row['CP1'],
								'cp2' => $row['CP2'],
								'id' => $row['id'],
								'cp' => $row['CP-Done'],
								'exp' => $row['EXP-Done'],
								'days' => $row['days']
							);
				}
			}
			
			$result->close ();
			
			return $resultset;
		} else {
			throw new dbException ( '500' );
		}
	}
	
	/**
	 * Get members which left / joined between the dates
	 * @param string $date1
	 * @param string $date2
	 * @param $showLeft set to true to show left members, otherwise joined
	 * @throws dbException
	 */
	public function getMemberDifference($date1, $date2, $showLeft) {
		$this->escapeData($date1);
		$this->escapeData($date2);
		$date_A = $showLeft ? $date1 : $date2;
		$date_B = $showLeft ? $date2 : $date1;
		if ($query = $this->db->prepare ( '
		SELECT names.name, m1.id, m1.date
		FROM member m1 
		JOIN (SELECT n1.id,n1.`name` FROM 
			(SELECT id,MAX(date) as maxdate 
				FROM member_names 
			GROUP BY id) as nEndDate 
			JOIN member_names AS n1 ON ( 
				n1.id = nEndDate.id 
				AND n1.date = nEndDate.maxdate 
			) 
		) names 
		ON m1.id = names.id
		WHERE m1.date LIKE "'.$date_A.'%" AND 
		m1.id NOT IN ( 
			SELECT id FROM member m2 
			WHERE m2.id = m1.id AND m2.date LIKE "'.$date_B.'%"
		)' )) { // Y-m-d G:i:s Y-m-d h:i:s
			$query->execute();
			$result = $query->get_result ();
			
			if (! $result) {
				throw new dbException ( $this->db->error, 500 );
			}
			
			if ($result->num_rows == 0) {
				$resultset = null;
			} else {
				$resultset = array ();
				while ( $row = $result->fetch_assoc () ) {
					$resultset[] = array(
						'id' => $row['id'],
						'name' => $row['name']
					);
				}
			}
			$result->close();
			
			return $resultset;
		} else {
			throw new dbException ( '500' );
		}
	}
	
	/**
	 * Get difference table
	 * @param string $date1
	 * @param string $date2
	 * @throws dbException
	 */
	public function getOverview($date1, $date2) {
		$this->escapeData($date1);
		$this->escapeData($date2);
		if ($query = $this->db->prepare ( 'select date,wins,losses,draws,members from clan where date between "'.$date1.'%" and DATE_ADD("'.$date2.'%" , INTERVAL 1 DAY) ORDER by date' )) { // Y-m-d G:i:s Y-m-d h:i:s
			$query->execute ();
			$result = $query->get_result ();
			
			if (! $result) {
				throw new dbException ( $this->db->error, 500 );
			}
			
			if ($result->num_rows == 0) {
				$resultset = null;
			} else {
				$resultset = array ();
				$wins = array();
				$losses = array();
				$draws = array();
				$member = array();
				while ( $row = $result->fetch_assoc () ) {
					$wins[] = array(
						'x' => $row['date'],
						'y' => $row['wins']
					);
					$losses[] = array(
						'x' => $row['date'],
						'y' => $row['losses']
					);
					$draws[] = array(
						'x' => $row['date'],
						'y' => $row['draws']
					);
					$member[] = array(
						'x' => $row['date'],
						'y' => $row['members']
					);
				}
				$resultset['wins'] = $wins;
				$resultset['losses'] = $losses;
				$resultset['draws'] = $draws;
				$resultset['member'] = $member;
			}
			$result->close();
			
			return $resultset;
		} else {
			throw new dbException ( '500' );
		}
	}
	
    /**
	 * Get difference of member from time to time
	 * @param string $date1
	 * @param string $date2
	 * @param number $memberID
	 * @throws dbException
	 */
	public function getMemberChange($date1, $date2, $memberID) {
		$this->escapeData($date1);
		$this->escapeData($date2);
		if ($query = $this->db->prepare ( 'select t1.id, t1.date,t1.exp,t1.cp,
		(t1.exp - (SELECT t2.exp FROM member t2
			WHERE t2.date < date_sub(t1.date, interval 10 hour) AND t2.id=t1.id 
			ORDER BY t2.date DESC LIMIT 1) ) AS `exp-diff`,
		(t1.cp - (SELECT t2.cp FROM member t2
			WHERE t2.date < date_sub(t1.date, interval 10 hour) AND t2.id=t1.id 
			ORDER BY t2.date DESC LIMIT 1) ) AS `cp-diff` 
		FROM member t1 
		where t1.id = ? 
		AND t1.date between "'.$date1.'%" and DATE_ADD("'.$date2.'%", INTERVAL 1 DAY) 
		ORDER by id,date' )) { // Y-m-d G:i:s Y-m-d h:i:s
			$query->bind_param ( 'i', $memberID );
			$query->execute ();
			$result = $query->get_result ();
			
			if (! $result) {
				throw new dbException ( $this->db->error, 500 );
			}
			
			if ($result->num_rows == 0) {
				$resultset = null;
			} else {
				$resultset = array ();
				$exp = array();
				$cp = array();
				$exp_diff = array();
				$cp_diff = array();
				while ( $row = $result->fetch_assoc () ) {
					$exp[] = array(
						'x' => $row['date'],
						'y' => $row['exp']
					);
					$cp[] = array(
						'x' => $row['date'],
						'y' => $row['cp']
					);
					$exp_diff[] = array(
						'x' => $row['date'],
						'y' => $row['exp-diff']
					);
					$cp_diff[] = array(
						'x' => $row['date'],
						'y' => $row['cp-diff']
					);
				}
				$resultset['cp'] = $cp;
				$resultset['exp'] = $exp;
				$resultset['cp_diff'] = $cp_diff;
				$resultset['exp_diff'] = $exp_diff;
			}
			$result->close();
			
			return $resultset;
		} else {
			throw new dbException ( '500' );
		}
	}
	
	/**
	 * Get difference of member from time to time
	 * @param string $key
	 * @throws dbException
	 */
	public function searchForMemberName($key) {
		$key = '%'.$key.'%';
		if ($query = $this->db->prepare ( 'SELECT name,id,date,updated from member_names WHERE name LIKE ? OR id LIKE ? order by id, date,updated ,name' )) { // Y-m-d G:i:s Y-m-d h:i:s
			$query->bind_param ( 'ss', $key, $key );
			$query->execute();
			$result = $query->get_result ();
			
			if (! $result) {
				throw new dbException ( $this->db->error, 500 );
			}
			
			if ($result->num_rows == 0) {
				$resultset = null;
			} else {
				$resultset = array ();
				while ( $row = $result->fetch_assoc () ) {
					$resultset[] = array(
						'id' => $row['id'],
						'name' => $row['name'],
						'date' => $row['date'],
						'updated' => $row['updated']
					);
				}
			}
			$result->close();
			
			return $resultset;
		} else {
			throw new dbException ( '500' );
		}
	}
	
	/**
	 * Get amount of entries in member table
	 * @param string $key
	 * @throws dbException
	 * @return integer/null
	 */
	public function getDBStats() {
		if ($query = $this->db->prepare ( 'SELECT COUNT(id) as `amount` from member' )) {
			$query->execute();
			$result = $query->get_result ();
			
			if (! $result) {
				throw new dbException ( $this->db->error, 500 );
			}
			
			if ($result->num_rows == 0) {
				$amount = null;
			} else {
				$amount = $result->fetch_assoc()['amount'];
			}
			$result->close();
			
			return $amount;
		} else {
			throw new dbException ( '500' );
		}
	}
	
	/**
	 * Get amount of entries in member_names table
	 * @param string $key
	 * @throws dbException
	 * @return integer/null
	 */
	public function getDBNameStats() {
		if ($query = $this->db->prepare ( 'SELECT COUNT(*) as `amount` from member_names' )) {
			$query->execute();
			$result = $query->get_result ();
			
			if (! $result) {
				throw new dbException ( $this->db->error, 500 );
			}
			
			if ($result->num_rows == 0) {
				$amount = null;
			} else {
				$amount = $result->fetch_assoc()['amount'];
			}
			$result->close();
			
			return $amount;
		} else {
			throw new dbException ( '500' );
		}
	}


	public function __destruct() {
		$this->db->close ();
	}
}
