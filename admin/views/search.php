<?php
class View_Search {

  function admin() {
    global $dict;
    ## Include model
    App::includeModel('models/search.php', 'search', true);
    $model = App::initAdminModel('search');

    $dict['search'] = $model->globalSearch();

    App::renderTwig('search.twig', $dict);
  }

}