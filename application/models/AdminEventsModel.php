<?php

namespace application\models;

use easymvc\base\Model;

include MODULES_PATH . 'matchPaths.php';

class AdminEventsModel extends Model
{
  protected $_table = 'events';
  protected $_imgUrl = PUBLIC_URL . 'events/images/';
  protected function moveImages($str)
  {
    $imgPaths = matchPaths($str);
    foreach ($imgPaths as $path) {
      $basename = basename($path);
      if (file_exists(TEMP_PATH . $basename)) {
        rename(TEMP_PATH . $basename, IMAGES_PATH . $basename);
        $str = str_replace($path, $this->_imgUrl . $basename, $str);
      }
    }
    return $str;
  }

  function get()
  {
    $query = "SELECT `id`, `title`, `status`, `date`, `location`, `banner`, `content` FROM {$this->_table}";
    $stmt = $this->_db->query($query);
    $data = $stmt->fetchAll();
    foreach ($data as &$row) {
      $row['banner'] = $this->_imgUrl . $row['banner'];
    }
    return $data;
  }

  function delete($id)
  {
    $deleteId = is_array($id) ? $id : [$id];

    $query_getImg = "select `banner`, `content` from {$this->_table} where `id` = ?";
    $getImg_stmt = $this->_db->prepare($query_getImg);
    $getImg_stmt->execute($deleteId);
    $fetchdata = $getImg_stmt->fetch();
    $imgPaths = matchPaths($fetchdata['content']);
    $imgPaths[] = $fetchdata['banner'];
    foreach ($imgPaths as $path) {
      $fileName = basename($path);
      $path = IMAGES_PATH . $fileName;
      @unlink($path);
    }
    
    $query_del = "DELETE FROM {$this->_table} WHERE `id` = ?";
    $del_stmt = $this->_db->prepare($query_del);
    $del_stmt->execute($deleteId);

    return true;
  }

  function post($values)
  {
    $values['content'] = $this->moveImages($values['content']);
    if (!empty($values['banner'])) {
      $ext = pathinfo($values['banner']['name'], PATHINFO_EXTENSION);
      $name = uniqid() . '.' . $ext;
      move_uploaded_file($values['banner']['tmp'] , IMAGES_PATH . $name);
      $values['banner'] = $name;
    }
    $valsAry = [];
    foreach ($values as $key => $val) {
      $valsAry[] = $val;
    }
    $query_insert = "insert into {$this->_table} (`cid`, `title`, `content`, `location`, `date`, `banner`) values (?, ?, ?, ?, ?, ?)";

    $insert_stmt = $this->_db->prepare($query_insert);
    $insert_stmt->execute($valsAry);
    if ($insert_stmt->rowCount() > 0) {
      return true;
    } else {
      return false;
    }
  }

  function put($values)
  {
    $values['content'] = $this->moveImages($values['content']);
    $newImgs = matchPaths($values['content']);

    $query_getImg = "select `banner`, `content` from {$this->_table} where `id` = ?";
    $getImg_stmt = $this->_db->prepare($query_getImg);
    $getImg_stmt->execute([$values['id']]);
    $fetchData = $getImg_stmt->fetch();
    $oldImgs = matchPaths($fetchData['content']);

    foreach ($oldImgs as $val) {
      if (!in_array($val, $newImgs)) {
        @unlink(IMAGES_PATH . basename($val));
      }
    }

    $query_update = "update {$this->_table} set `title` = ?, `content` = ?, `location` = ?, `date` = ? ";
    $valsAry = [$values['title'], $values['content'], $values['location'], $values['date']];
    if (!empty($values['banner'])) {
      $ext = pathinfo($values['banner']['name'], PATHINFO_EXTENSION);
      $name = uniqid() . '.' . $ext;
      move_uploaded_file($values['banner']['tmp'], IMAGES_PATH . $name);
      @unlink(IMAGES_PATH . basename($fetchData['banner']));
      $valsAry[] = $name;
      $query_update .= ", `banner` = ? ";
    }

    $valsAry[] = $values['id'];
    $query_update .= "where `id` = ?";
    $update_stmt = $this->_db->prepare($query_update);
    $update_stmt->execute($valsAry);
    
    if ($update_stmt->rowCount() > 0) {
      return true;
    } else {
      return false;
    }
  }
}