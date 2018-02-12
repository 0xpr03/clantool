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
        (`m1`.`cp`-`m2`.`cp`) AS `CP-Done`, DATEDIFF(m1.date,m2.date) AS `days`,
        cpdiff.`cp_by_exp`
        FROM member as m2 
        RIGHT JOIN member AS m1 ON m1.id = m2.id 
            AND m1.date LIKE "'.$date2.'%" 
        JOIN (SELECT n1.id,n1.`name` FROM 
                (SELECT id,MAX(updated) as maxdate 
                FROM member_names 
                GROUP BY id) as nEndDate 
                JOIN member_names AS n1 ON ( 
                    n1.id = nEndDate.id 
                    AND n1.updated = nEndDate.maxdate 
                ) 
        ) names ON m2.id = names.id 
        LEFT JOIN (SELECT id, SUM(CASE WHEN cpdiff2.`cp-exp` > '.MAX_CP_DAY.' THEN '.MAX_CP_DAY.' ELSE cpdiff2.`cp-exp` END) AS `cp_by_exp`
                FROM
                    (SELECT t1.id,(t1.exp - (SELECT t2.exp FROM member t2
                        WHERE t2.date < date_sub(t1.date, interval '.MAX_CP_DAY.' hour) AND t2.id=t1.id 
                        ORDER BY t2.date DESC LIMIT 1) ) DIV '.EXP_TO_CP.' AS `cp-exp`
                    FROM member t1 
                    WHERE t1.date between DATE_ADD("'.$date1.'%", INTERVAL 1 DAY) and DATE_ADD("'.$date2.'%", INTERVAL 1 DAY)
                ) cpdiff2
                GROUP BY id
        ) cpdiff ON cpdiff.id = m2.id
        WHERE m2.date LIKE "'.$date1.'%" OR ( m2.date >  "'.$date1.'%" AND m2.date < "'.$date2.'%" ) 
        GROUP BY `id` ORDER BY `cp_by_exp`,`CP-Done`, `EXP-Done`' )) { // Y-m-d G:i:s Y-m-d h:i:s
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
                                'days' => $row['days'],
                                'cp_by_exp' => $row['cp_by_exp']
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
     * Get overview data
     * @param string $date1
     * @param string $date2
     * @throws dbException
     */
    public function getOverview($date1, $date2) {
        $this->escapeData($date1);
        $this->escapeData($date2);
        if ($query = $this->db->prepare ( 'SELECT `date`,`wins`,`losses`,`draws`,`members`FROM `clan` WHERE `date` BETWEEN "'.$date1.'%" AND DATE_ADD("'.$date2.'%" , INTERVAL 1 DAY) ORDER BY `date`' )) { // Y-m-d G:i:s Y-m-d h:i:s
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
        if ($query = $this->db->prepare ( 'SELECT t1.id, t1.date,t1.exp,t1.cp,
        (t1.exp - (SELECT t2.exp FROM member t2
            WHERE t2.date < date_sub(t1.date, interval 10 hour) AND t2.id=t1.id 
            ORDER BY t2.date DESC LIMIT 1) ) AS `exp-diff`,
        (t1.cp - (SELECT t2.cp FROM member t2
            WHERE t2.date < date_sub(t1.date, interval 10 hour) AND t2.id=t1.id 
            ORDER BY t2.date DESC LIMIT 1) ) AS `cp-diff` 
        FROM member t1 
        WHERE t1.id = ? 
        AND t1.date BETWEEN DATE_ADD("'.$date1.'%", INTERVAL 1 DAY) AND DATE_ADD("'.$date2.'%", INTERVAL 1 DAY) 
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
        if ($query = $this->db->prepare ( 'SELECT `name`,`id`,`date`,`updated` FROM `member_names` WHERE `name` LIKE ? OR id LIKE ? ORDER BY `id`,`date`,`updated`,`name`' )) { // Y-m-d G:i:s Y-m-d h:i:s
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

    /**
     * Get dates for selection where there are noted missing dates
     * @param $date1 first date
     * @param $date2 second date
     * @return array of missing dates
     */
    public function getMissingEntries($date1,$date2) {
        if ($query = $this->db->prepare ( 'SELECT DATE(`date`) as date from `missing_entries` WHERE date BETWEEN ? AND ?')) { // Y-m-d G:i:s Y-m-d h:i:s
            $query->bind_param ( 'ss', $date1, $date2 );
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
                    $resultset[] = $row['date'];
                }
            }
            $result->close();
            
            return $resultset;
        } else {
            throw new dbException ( '500' );
        }
        
    }


    public function __destruct() {
        $this->db->close ();
    }
}
