<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Paginator;
// for Doctrine annotation
use Zend\View\Model\JsonModel;
use Form\Entity\FormData;
use Zend\Mail;
use Zend\Session\Container;

class IndexController extends AbstractActionController {

    public function indexAction() {
        /* paging... */
        //die('page');
//return $this->redirect()->toRoute('zfcuser/login');    
        /* handle searches */
        $search = "";
        $getparams = $this->getRequest()->getQuery();
        $search = $getparams['s'];

        if ($search !== null)
            return $this->searchAction();
        $entityManager = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');

        // $lang = $this->params()->fromRoute('lang');
        $lang = $this->params()->fromRoute('title');
        if ($lang == "en") {
            $home = $entityManager->getRepository('Home\Entity\Home')->findOneBy(array('id' => '24'));

            /* set to english */
            $userSession = new Container('lang');
            $userSession->language = 'en';


            $view = new ViewModel(array(
                'lang' => $lang,
                'home' => $home,
                'homeSlides' => "",
                'homeTeamevents' => "",
                'p' => "",
                'posts' => "",
                'canonical' => $this->getCanonical(),
            ));
            $view->setTemplate('application/index/index.phtml');
            return $view;
        } else {
            $userSession = new Container('lang');
            $userSession->language = 'de';
        }
        try {
            $home = $entityManager->getRepository('Home\Entity\Home')->findOneBy(array('active' => '1'));
            $slides = explode(",", $home->getSlides());
            $teamevents = explode(",", $home->getTeamevents());
            $products = explode(",", $home->getProducts());


            foreach ($slides as $slide) {
                if ($slide !== "") {
                    $homeSlides[] = $entityManager->getRepository('Home\Entity\Slides')->findOneBy(array("id" => $slide));
                }
            }
            $posts = $entityManager->getRepository('Home\Entity\Posts')->findAll();

            foreach ($teamevents as $teamevent) {
                if ($teamevent !== "") {
                    $homeTeamevents[] = $entityManager->getRepository('DynamicPage\Entity\DynamicPage')->findOneBy(array("id" => $teamevent));
                }
            }
            foreach ($products as $product) {
                if ($product !== "") {
                    $homeProducts[] = $entityManager->getRepository('Products\Entity\ProductLevelFirst')->findOneBy(array("id" => $product));
                }
                if (!isset($homeProducts))
                    $homeProducts = null;
            }
        } catch (\Exception $ex) {
            echo $ex->getMessage(); // this never will be seen if you don't comment the redirect
//return $this->redirect()->toRoute('home');
        }


        $view = new ViewModel(array(
            'menu' => $this->getMenuAction()->menus,
            'homeSlides' => $homeSlides,
            'homeTeamevents' => $homeTeamevents,
            'p' => $homeProducts,
            'home' => $home,
            'posts' => $posts,
            'canonical' => $this->getCanonical(),
            'lang' => $this->getLanguage(),
        ));
        $view->setTemplate('application/index/index.phtml');
        return $view;
    }

    public function pageAction() {
        //die('page6');
        $title = $this->params()->fromRoute('title');

        $entityManager = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
        try {
            $repository = $entityManager->getRepository('StaticPage\Entity\StaticPage');
            $staticPage = $repository->findOneBy(array('pageName' => $title));
        } catch (\Exception $ex) {
            echo $ex->getMessage(); // this never will be seen if you don't comment the redirect
            return $this->redirect()->toRoute('home');
        }
        if (!isset($staticPage)) {
            //$this->getResponse()->setStatusCode(404);
            return $this->newsAction();
            // return $this->redirect()->toRoute('notfound');
        }
        $sidebar = "";
		if (isset($this->getSidebarByPage('SP', $staticPage->getId())->sidebar))
        $sidebar = $this->getSidebarByPage('SP', $staticPage->getId())->sidebar;

        if (($sidebar !== "")&&($sidebar!== null)) {
            $definition = ($sidebar->getdefinition());
            $definition = (json_decode($definition));
            $sideform = "";
            foreach ($definition as $d):

                foreach ($d as $i => $v):
                    if ($i == "'d'") {
                        $repository = $entityManager->getRepository('Form\Entity\Form');
                        //$sideform = $repository->findOneBy(array('name' => $v));
                        /* default = 4 */
                        if ($this->getLanguage() == "en")
                            $sideform = $repository->findOneBy(array('id' => 15));
                        else
                            $sideform = $repository->findOneBy(array('id' => 4));
                    }
                endforeach;

            endforeach;
        } else {
            $definition = "";
            $sideform = "";
        }



        $view = new ViewModel(array(
            'content' => $staticPage->getPageContent(),
            'title' => $staticPage->getTitle(),
            'menu' => $this->getMenuAction()->menus,
            'sidebar' => $definition,
            'sideform' => $sideform,
            'page' => $staticPage,
            'canonical' => $this->getCanonical(),
            'lang' => $this->getLanguage(),
        ));
        $view->setTemplate('application/index/page.phtml');
        return $view;
    }

    public function productAction() {
        $title = $this->params()->fromRoute('title');
        $title = str_replace("%20", " ", $title);

        $entityManager = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
        try {
            $repository = $entityManager->getRepository('Products\Entity\ProductLevelFirst');
            $product = $repository->findOneBy(array('url' => $title));
        } catch (\Exception $ex) {
            echo $ex->getMessage(); // this never will be seen if you don't comment the redirect
            return $this->redirect()->toRoute('notfound');
//return $this->redirect()->toRoute('zfcadmin/staticpage/default', array('controller' => 'index', 'action' => 'index'));
        }

        if (!isset($product)) {

            $this->getResponse()->setStatusCode(404);
            return $this->redirect()->toRoute('notfound');
        }
        $a = ($product->getPrdRegions());
        $regions = array();
        if (!empty($product->prdRegions)) {
            foreach ($product->prdRegions as $key => $region) {
                $regions = $entityManager->getRepository('Products\Entity\Regions')->findBy(
                        array('id' => $region), array());
            }
        }
        $id = $product->getId();
        if (isset($id)) {
            $prd = $entityManager->getRepository('Products\Entity\ProductLevelFirst')->find(array('id' => $id));
            if (!empty($prd)) {
                if ($prd->getParent() != NULL) {
                    $seId = $prd->getParent();
                } else {
                    $seId = $id;
                }
            }
        }
        if (!empty($imagesFromDb)) {
            $files['images'] = array_merge($files, $imagesFromDb);
        }

        $cities = $product->getPrdCities();
        $addFields = $product->getAddFields();
        $author = $product->getAuthor();
        $formid = $this->getFormByAssignment($product->getId(), 'product');

        $repository = $entityManager->getRepository('Form\Entity\Form');
        $form = $repository->findOneBy(array('id' => $formid));

        /* fetch the sidebar information .... */
        $sidebar = "";
        $sidebar = $this->getSidebarByPage('PR', null)->sidebar;
        $definition = ($sidebar->getdefinition());
        $definition = (json_decode($definition));
        $sideform = "";
        foreach ($definition as $d):

            foreach ($d as $i => $v):
                if ($i == "'d'") {
                    $repository = $entityManager->getRepository('Form\Entity\Form');
                    //$sideform = $repository->findOneBy(array('name' => $v));
                    /* default, de:id = 4, en:15 */
                    if ($this->getLanguage() == "en")
                        $sideform = $repository->findOneBy(array('id' => 15));
                    else
                        $sideform = $repository->findOneBy(array('id' => 4));
                }
            endforeach;
        endforeach;

        /* end of sidebar fetch */

        $view = new ViewModel(array(
            'content' => $product->getProductName(),
            'title' => $product->getProductName(),
            'region' => $a,
            'durations' => $product->getDurations(),
            'from' => $product->getPaxFrom(),
            'to' => $product->getPaxTo(),
            'cid' => $product->getEventCid(),
            "regions" => $regions,
            "images" => "",
            "product" => $product,
            "cities" => $cities,
            "additionalFields" => $addFields,
            "author" => $author,
            'menu' => $this->getMenuAction()->menus,
            'sidebar' => $definition,
            'sideform' => $sideform,
            'form' => $form,
            'canonical' => $this->getCanonical(),
            'lang' => $this->getLanguage(),
                )
        );
        $view->setTemplate('application/index/product.phtml');
        return $view;
    }

    public function searchAction() {
        //die('page4');
        $entityManager = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');

        $title = $this->params()->fromRoute('title');
        $pn = $this->params()->fromRoute('city');
        $getparams = $this->getRequest()->getQuery();
        $search = $getparams['s'];

        $repository = $entityManager->getRepository('Products\Entity\ProductLevelFirst');

        //$search = $request->getPost('search');
        $products = $repository->createQueryBuilder('c')
                ->where('c.bodyText LIKE :search')
                ->setParameter('search', "%$search%")
                ->getQuery()
                ->getResult();

        if (is_array($products)) {
            $paginator = new Paginator\Paginator(new Paginator\Adapter\ArrayAdapter($products));
        } else {
            $paginator = $products;
        }

        $paginator->setItemCountPerPage(10);

        $paginator->setCurrentPageNumber($pn);
        $count = count($products);



        $view = new ViewModel(array(
            'menu' => $this->getMenuAction()->menus,
            'items' => $paginator,
            'search' => $search,
            'count' => $count,
            'canonical' => $this->getCanonical(),
            'lang' => $this->getLanguage(),
        ));
        $view->setTemplate('application/index/search.phtml');
        return $view;
    }

    public function newsAction() {
        $title = $this->params()->fromRoute('title');
        $pn = ($this->params()->fromRoute('page'));
        $res = "";

        //die("in news after all");
        $getparams = $this->getRequest()->getQuery();
        $sortBy = $getparams['sortieren'];
        $order = $getparams['absteigend'];
        $sort_to_add = "";
        if (isset($sortBy) && $sortBy == 'preis'):
            $sort_to_add = " order by p.price ";

            if (isset($order) && $order == 1):
                $sort_to_add .= " DESC";
            endif;
        else :
            $sort_to_add = "ORDER BY RAND() ";
        endif;

        $entityManager = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
        $repository = $entityManager->getRepository('News\Entity\News');

        if (!is_numeric($this->params()->fromRoute('page'))) {
            $type = "item";
            try {
                $news = $repository->findBy(array('url' => $this->params()->fromRoute('title')));
                if (count($news) == 0)
                    return $this->eventTypeAction();
                if ($news[0]->getEvent()) {
                    $sql = " 
                select p.* , i.*
                from product_level_first p , 
                images i,
                products_events pe, 
                event_type e 
                where pe.product_id = p.id 
                and i.product_id = p.id
                and i.main = '1' 
                and pe.event_id = e.id 
                and e.id = '" . $news[0]->getEvent() . "'
                " . $sort_to_add . "
                    limit 20
             ";

                    $stmt = $entityManager->getConnection()->prepare($sql);
                    $stmt->execute();
                    $res = $stmt->fetchAll();
                }
            } catch (\Exception $ex) {
                echo $ex->getMessage();
            }
        } else {
            $type = "list";
            $news = $repository->findAll();

            $news = $repository->createQueryBuilder('c')
                    ->orderBy('c.post_date', 'DESC')
                    ->getQuery()
                    ->getResult();
        }

        /* if (((!isset($title) || $title == "") || $this->params()->fromRoute('city') == "p") && is_int($this->params()->fromRoute('page'))) {
          $type = "list";
          $news = $repository->findAll();
          $news = $repository->createQueryBuilder('c')
          ->orderBy('c.post_date', 'DESC')
          ->getQuery()
          ->getResult();
          } else {

          }
         */
        if (is_array($news)) {
            $paginator = new Paginator\Paginator(new Paginator\Adapter\ArrayAdapter($news));
        } else {
            $paginator = $news;
        }

        $paginator->setItemCountPerPage(10);

        $paginator->setCurrentPageNumber($pn);

        $view = new ViewModel(array(
            'menu' => $this->getMenuAction()->menus,
            'news' => $paginator,
            'type' => $type,
            'products' => $res,
            'order' => $order,
            'canonical' => $this->getCanonical(),
            'lang' => $this->getLanguage(),
        ));
        $view->setTemplate('application/index/news.phtml');
        return $view;
    }

    public function formAction() {
        if ($this->params()->fromRoute('title') == "news")
            return $this->newsAction();
        /* lang redirect... :( */
        if ($this->params()->fromRoute('title') == "en")
            return $this->indexAction();
        $title = $this->params()->fromRoute('title');
        $entityManager = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
        $repository = $entityManager->getRepository('Form\Entity\Form');

        $form = $repository->findBy(array('url' => $title));
        if (count($form) == 0)
            return $this->pageAction();
        //return $this->redirect()->toRoute('notfound');

        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = ($request->getPost());

            $ua = ($_SERVER['HTTP_USER_AGENT']);
            $data["user-agent"] = $ua;
            $formdata = new FormData;
            if (!isset($data["id"]))
                $data["id"] = "";
            $formdata->setForm_id($data["id"]);
            $formdata->setData(json_encode($data));

            $entityManager->persist($formdata);
            $entityManager->flush();
            /* $this->sendMail(); //needs configuration */
            //return $this->redirect()->toRoute('home');
            return $this->redirect()->toRoute('application/page', array('title' => 'danke-schoen'));
        }


        $view = new ViewModel(array(
            'menu' => $this->getMenuAction()->menus,
            'form' => $form,
            'canonical' => $this->getCanonical(),
            'lang' => $this->getLanguage(),
        ));
        $view->setTemplate('application/index/form.phtml');
        return $view;
    }

    public function eventTypeAction() {
        /* here goes pagination */
        if ($this->params()->fromRoute('title') == "page")
            return $this->searchAction();
        $city = "";
        $title = $this->params()->fromRoute('title');
        $city = $this->params()->fromRoute('city');
        $title = str_replace("%20", " ", $title);
        if (isset($city))
            $search = $title . "/" . $city;
        else {
            $search = $title;
            $city = "";
        }
        $getparams = $this->getRequest()->getQuery();
        $sortBy = $getparams['sortieren'];
        $order = $getparams['absteigend'];
        $sort_to_add = "";
        if (isset($sortBy) && $sortBy == 'preis'):
            $sort_to_add = " order by p.price ";

            if (isset($order) && $order == 1):
                $sort_to_add .= " DESC";
            endif;
        else :
            $sort_to_add = "ORDER BY RAND() ";
        endif;

        $city = "";
//die($city);
        $entityManager = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
        try {

            $repository = $entityManager->getRepository('DynamicPage\Entity\DynamicPage');
            $page = $repository->findOneBy(array('url' => urldecode($search)));

            if (!isset($page)) {
                //die("not found page");
                return $this->productAction();
            }
            $parentPage = $repository->findOneBy(array('id' => $page->getParentId()));
            $childrenPages = $repository->findBy(array('parentId' => $page->getId()));

            if ($page->getParentID() !== 0)
                $city = str_replace($parentPage->getTitle(), "", $page->getTitle());
            /*
              try to find harcoded pages -
             * l1 
             * Berlin
              München
              Hamburg
              Frankfurt
              Köln
              Leipzig
              Essen
              Hannover
              Potsdam
              l2
             * Betriebsausflug with the current city 
              Schnitzeljagd with the current city
              Teambuilding with the current city
              Weihanchstsfeier with the current city
              Kick Off with the current city
             * */

            $cities = array('Berlin',
                'München',
                'Hamburg',
                'Frankfurt',
                'Köln',
                'Leipzig',
                'Essen',
                'Hannover',
                'Potsdam');
            $dpages = array('Betriebsausflug', 'Schnitzeljagd', 'Teambuilding', 'Weihnachtsfeier', 'Kick Off');

            $repository = $entityManager->getRepository('Products\Entity\EventType');
            $et = $repository->findBy(array('id' => $page->getEvent()));

            $formid = $this->getFormByAssignment($page->getId(), 'page');
            $repository = $entityManager->getRepository('Form\Entity\Form');
            $form = $repository->findBy(array('id' => $formid));

            $sp = "";
            if (!is_null($page->getProducts()) && $page->getProducts() != "") {


                $sql = " 
                select p.* , i.*
                from product_level_first p , 
                images i
                where i.product_id = p.id
                and i.main = '1' 
                and p.id in (" . rtrim($page->getProducts(), ",") . ")
             ";

                $stmt = $entityManager->getConnection()->prepare($sql);
                $stmt->execute();
                $sp = $stmt->fetchAll();
            }


            if (isset($city) && $city !== "") {
                $city = strtolower($city);
                $sql = " 
                select distinct p.* , i.*
                from product_level_first p , 
                images i,
                product_city pc,
                event_type e ,
                products_events pe, 
                city c
                where i.product_id = p.id
                and i.main = '1' 
                and pc.product_id = p.id
                and c.id = pc.city_id
                and p.level = '2'
                and pe.event_id = e.id
                and pe.product_id = p.id 
                and e.id = '" . $et[0]->getId() . "'
                and lower(c.city) = '" . trim($city) . "'
                     " . $sort_to_add . "
             ";
            } else {
                $sql = " 
                select p.* , i.*
                from product_level_first p , 
                images i,
                products_events pe, 
                event_type e 
                where pe.product_id = p.id 
                and i.product_id = p.id
                and i.main = '1' 
                and p.level = '2'
                and pe.event_id = e.id 
                and e.id = '" . $et[0]->getId() . "'
                " . $sort_to_add . "
            ";
            }

            if ($page->getParentID() === 0) {//no parent = level 1 listing!
                $sql = "
              select p.* , i.*
              from product_level_first p ,
              images i,
              products_events pe,
              event_type e
              where pe.product_id = p.id
              and i.product_id = p.id
              and i.main = '1'
              and pe.event_id = e.id
              and p.level = '1'
              and p.only_level_two <> 1
              and e.id = '" . $et[0]->getId() . "'
              " . $sort_to_add . "
              ";
            }

            $stmt = $entityManager->getConnection()->prepare($sql);
            $stmt->execute();
            $res = $stmt->fetchAll();

            /* fetch the sidebar information .... */
            $sidebar = "";
			if(isset($this->getSidebarByPage('DP', $page->getId())->sidebar))
            $sidebar = $this->getSidebarByPage('DP', $page->getId())->sidebar;

            if (($sidebar !== null) && ($sidebar !== "")) {
                $definition = ($sidebar->getdefinition());
                $definition = (json_decode($definition));
                $sideform = "";
                foreach ($definition as $d):

                    foreach ($d as $i => $v):
                        if ($i == "'d'") {
                            $repository = $entityManager->getRepository('Form\Entity\Form');
                            //$sideform = $repository->findBy(array('name' => $v));
                            /* default 4 */
                            if ($this->getLanguage() == "en")
                                $sideform = $repository->findOneBy(array('id' => 15));
                            else
                                $sideform = $repository->findOneBy(array('id' => 4));
                        }
                    endforeach;
                endforeach;
            } else {
                $definition = "";
                $sideform = "";
            }
            /* end of sidebar fetch */
        } catch (\Exception $ex) {
            echo $ex->getMessage(); // this never will be seen if you don't comment the redirect
        }



        $view = new ViewModel(array(
            "et" => $et[0],
            'page' => $page,
            'selectedProducts' => $sp,
            'images' => "",
            'products' => $res,
            'order' => $order,
            'parent' => isset($parentPage) ? $parentPage : null,
            'children' => $childrenPages,
            'city' => $city,
            'menu' => $this->getMenuAction()->menus,
            'sidebar' => $definition,
            'sideform' => $sideform,
            'form' => $form,
            'cities' => $cities,
            'dpages' => $dpages,
            'canonical' => $this->getCanonical(),
            'lang' => $this->getLanguage(),
        ));
        $view->setTemplate('application/index/event-type.phtml');
        return $view;
    }

    public function getCommentAction() {
        $sql = " 
                select text as comment, author as author
                from comments
                order by rand()
                limit 1;
             ";
        $entityManager = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
        $stmt = $entityManager->getConnection()->prepare($sql);
        $stmt->execute();
        $res = $stmt->fetchAll();

        $result = new JsonModel(array(
            'comment' => $res[0],
        ));
        return $result;
    }

    public function notfoundAction() {
        $this->getResponse()->setStatusCode(404);
        return new ViewModel(array('menu' => $this->getMenuAction()->menus, 'lang' => $this->getLanguage(),));
    }

    public function getMenuAction() {
        $menuPages = "";
        $entityManager = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
        try {
            $menus = $entityManager->getRepository('Menu\Entity\Menu')->findBy(array(), array('position' => 'ASC'));

            foreach ($menus as $c => $menu):
                $pages = $menu->getPages();
                $menuTitle = $menu->getName();

                if ($menu->getItemsorder() !== "") {/* order - let's order them */
                    $menuPages[$c]['name'] = $menuTitle;
                    $menuPages[$c]['link'] = $menu->getUrl();
                    foreach (json_decode($menu->getItemsorder()) as $i => $page) {
                        if ($page !== ""):
                            $repository = $entityManager->getRepository('DynamicPage\Entity\DynamicPage');
                            $res = $repository->findBy(array('id' => $page));
                            if (isset($res[0])) {
                                if (is_null($res[0]->getDisplayname()) || $res[0]->getDisplayname() == "")
                                    $title = $res[0]->getTitle();
                                else
                                    $title = $res[0]->getDisplayname();
                                $menuPages[$c]['items'][$i]['title'][] = $title;
                                $menuPages[$c]['items'][$i]['link'][] = $res[0]->getUrl();
                            }
                        endif;
                    }
                } else {/* no order , keep it old way */
                    $pages = explode(',', $pages);
                    $menuPages[$c]['name'] = $menuTitle;
                    $menuPages[$c]['link'] = $menu->getUrl();
                    foreach ($pages as $i => $page):
                        if ($page !== ""):
                            $repository = $entityManager->getRepository('DynamicPage\Entity\DynamicPage');
                            $res = $repository->findBy(array('id' => $page));
                            if (isset($res[0])) {
                                if (is_null($res[0]->getDisplayname()) || $res[0]->getDisplayname() == "")
                                    $title = $res[0]->getTitle();
                                else
                                    $title = $res[0]->getDisplayname();
                                $menuPages[$c]['items'][$i]['title'][] = $title;
                                $menuPages[$c]['items'][$i]['link'][] = $res[0]->getUrl();
                            }
                        endif;
                    endforeach;
                }
            endforeach;
//            die();
        } catch (\Exception $ex) {
            echo $ex->getMessage();
        }
        $result = new JsonModel(array(
            'menus' => $menuPages,
        ));
        return $result;
    }

    public function getSidebarByPage($assignment, $id = null) {
// $assignments = ['PR' => 'Products', 'NP' => 'News posts', 'DP' => 'Dynamic Pages', 'SP' => 'Static Pages'];
        $entityManager = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
        $sbs = $entityManager->getRepository('Sidebars\Entity\Sidebars')->findBy(array('assignment' => $assignment));

        /* but check language - enDP = 13, enPR=12 */
        if ($this->getLanguage() == "en") {
            if ($assignment == "PR") {
                $sbs = $entityManager->getRepository('Sidebars\Entity\Sidebars')->findOneBy(array('id' => 12));
            } else if ($assignment == "DP") {
                $sbs = $entityManager->getRepository('Sidebars\Entity\Sidebars')->findOneBy(array('id' => 13));
            }

            $result = new JsonModel(array(
                'sidebar' => $sbs,
            ));
			return $result;
        } else {
            $rv = "";
			
            if ($assignment == "SP" ) {
                foreach ($sbs as $sb):
                    foreach (json_decode($sb->getAssignmentDetail()) as $s):
                        if ($s == $id) {
                            $rv = $sb;
                        }
                    endforeach;
                endforeach;
			}
			else 
			if( $assignment == "DP")
				{
				  
				  $sbs = $entityManager->getRepository('Sidebars\Entity\Sidebars')->findOneBy(array('id' => 1));
				  $result = new JsonModel(array(
                    'sidebar' => $sbs,
                ));
                 return $result;
				
            } 
			else 
			{
                $sbs = $entityManager->getRepository('Sidebars\Entity\Sidebars')->findOneBy(array('id' => 2));
                $result = new JsonModel(array(
                    'sidebar' => $sbs,
                ));
                 return $result;
            }

            $result = new JsonModel(array(
                'sidebar' => $rv,
            ));
        }
       
    }

    public function getFormByAssignment($id, $type) {
        $entityManager = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');

//        $formAssignment = $entityManager->getRepository('Form\Entity\FormAssignment')->findBy(array('assignment_id' => $id, 'assignment_type' => $type));
//        
//        $forma = $entityManager->getRepository('Form\Entity\Form')->findBy(array('active' => 1));
//        if (count($formAssignment) == 1) {
//            $form = $entityManager->getRepository('Form\Entity\Form')->findOneBy(array('id' => $formAssignment[0]->getForm_id()));
//        } else {
//            $form = $entityManager->getRepository('Form\Entity\Form')->findOneBy(array('active' => 1));
//        }
//        //var_dump($form);
//        $forma = $entityManager->getRepository('Form\Entity\Form')->findOneBy(array('id' => 4));
//        $form = $entityManager->getRepository('Form\Entity\Form')->findOneBy(array('id' => 4));
//        /* default form is with id 4... */
//        if (count($form) == 0)
//            return $forma[0]->getId();
//        else
//            return $form[0]->getId();

        /* default 4 */
        if ($this->getLanguage() == "en")
            $form = $entityManager->getRepository('Form\Entity\Form')->findOneBy(array('id' => 15));
        else
            $form = $entityManager->getRepository('Form\Entity\Form')->findOneBy(array('id' => 4));
        // $form = $entityManager->getRepository('Form\Entity\Form')->findOneBy(array('id' => 4));
        return $form->getId();
    }

    public function getCanonical() {
        $title = $this->params()->fromRoute('title');
        $city = $this->params()->fromRoute('city');

        return "http://www.teamgeist.com" . "/" . $title . "/" . $city;
    }

    public function sendMail() {
        $mail = new Mail\Message();
        $mail->setBody('This is the text of the email.');
        $mail->setFrom('klm@dir.bg', 'Sender\'s name');
        $mail->addTo('joelynnturner@gmail.com', 'Name of recipient');
        $mail->setSubject('TestSubject');

        $transport = new Mail\Transport\Sendmail();
        $transport->send($mail);
    }

    public function getLanguage() {
        $userSession = new Container('lang');
        $lang = $userSession->language;
        $translator = $this->getServiceLocator()->get('translator');
        if ($lang == "de") {
            $translator->setLocale('de_DE');
        } else {
            $translator->setLocale('en_US');
        }

        return $lang;
    }

}
