<?php

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

class table_dsu_paulsignemot extends discuz_table
{
  public function __construct()
  {
    $this->_table = 'dsu_paulsignemot';
    $this->_pk = 'id';

    parent::__construct();
  }

  public function getemotbyqdxq($qdxq)
  {
    $wheresql = 'WHERE ' . DB::field('qdxq', $qdxq, '=');
    return DB::fetch_first('SELECT id FROM %t $i ', array($this->_table, $wheresql));
  }

  public function getemotdata($order = 'displayorder', $sort = 'ASC')
  {
    $order = in_array($order, array('id', 'displayorder', 'qdxq', 'count', 'name'), true) ? $order : 'displayorder';
    $sort = strtoupper($sort) === 'DESC' ? 'DESC' : 'ASC';
    $ordersql = " ORDER BY $order $sort ";
    return DB::fetch_all('SELECT * FROM %t %i ', array($this->_table, $ordersql));
  }

  public function getemotrank($start = 0, $limit = 5, $order = 'count', $sort = 'DESC')
  {
    $order = in_array($order, array('id', 'displayorder', 'qdxq', 'count', 'name'), true) ? $order : 'count';
    $sort = strtoupper($sort) === 'ASC' ? 'ASC' : 'DESC';
    $ordersql = " ORDER BY $order $sort ";
    return DB::fetch_all('SELECT count,name FROM %t %i ' . DB::limit($start, $limit), array($this->_table, $ordersql));
  }

  public function cleartable()
  {
    return DB::query('TRUNCATE TABLE %t ', array($this->_table));
  }

  public function updatebyqdxq($qdxq)
  {
    return DB::query('UPDATE %t SET count=count+1 WHERE qdxq=%s', array($this->_table, $qdxq));
  }

  public function clearcount()
  {
    return DB::query('UPDATE %t SET count=0 ', array($this->_table));
  }

  public function checktableexist()
  {
    $query = DB::query("SHOW TABLES LIKE '%t'", array($this->_table));
    return DB::num_rows($query);
  }

}
