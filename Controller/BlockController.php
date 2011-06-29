<?php

/*

 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Kitpages\CmsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

use Kitpages\CmsBundle\Entity\Block;
use Kitpages\CmsBundle\Form\BlockType;
use Kitpages\CmsBundle\Model\BlockManager;
use Kitpages\CmsBundle\Model\CmsManager;

class BlockController extends Controller
{
    
   
    public function viewAction()
    {
        return $this->render('KitpagesCmsBundle:Block:view.html.twig');
    }
    
    public function createAction()
    {
        $block = new Block();

        // build template list
        $templateList = $this->container->getParameter('kitpages_cms.block.template.template_list');
        $selectTemplateList = array();
        foreach ($templateList as $key => $template) {
            $selectTemplateList[$key] = $template['name'];
        }

        // build basic form
        $builder = $this->createFormBuilder($block);
        $builder->add('slug', 'text');
        $builder->add('template', 'choice',array(
            'choices' => $selectTemplateList,
            'required' => true
        ));
        // get form
        $form = $builder->getForm();
        
        $request = $this->get('request');
        if ($request->getMethod() == 'POST') {
            $form->bindRequest($request);

            if ($form->isValid()) {
                $block->setBlockType('edito');
                $block->setIsActive(true);
                $block->setIsPublished(false);
                $em = $this->get('doctrine')->getEntityManager();
                $em->persist($block);
                $em->flush();

                return $this->redirect($this->generateUrl('kitpages_cms_block_edit', array('id' => $block->getId() )));
            }
        }
        return $this->render('KitpagesCmsBundle:Block:create.html.twig', array(
            'form' => $form->createView()
        ));
    }

    public function editAction($id)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $block = $em->getRepository('KitpagesCmsBundle:Block')->find($id);
        if (!$block->getData()) {
            $block->setData(array('root'=>null));
        }
        // build template list
        $templateList = $this->container->getParameter('kitpages_cms.block.template.template_list');
        $selectTemplateList = array();
        foreach ($templateList as $key => $template) {
            $selectTemplateList[$key] = $template['name'];
        }
        $twigTemplate = $templateList[$block->getTemplate()]['twig'];
        
        // build basic form
        $builder = $this->createFormBuilder($block);
        $builder->add('slug', 'text');
        $builder->add('template', 'choice',array(
            'choices' => $selectTemplateList,
            'required' => true
        ));
        $builder->add('isActive', 'checkbox');

        // build custom form
        $className = $templateList[$block->getTemplate()]['class'];
        $builder->add('data', 'collection', array(
           'type' => new $className(),
        ));
        
        // get form
        $form = $builder->getForm();
        
        // persist form if needed
        $request = $this->get('request');
        if ($request->getMethod() == 'POST') {
            $form->bindRequest($request);

            if ($form->isValid()) {
                $em = $this->get('doctrine')->getEntityManager();
                $em->persist($block);
                $em->flush();

                return $this->redirect($this->generateUrl('kitpages_cms_block_edit_success'));
            }
        }
        $view = $form->createView();
        //echo '<pre>'.print_r($view, true).'</pre>';
        return $this->render($twigTemplate, array(
            'form' => $form->createView(),
            'id' => $block->getId()
        ));
    }
    

    public function widgetAction($label) {
        // récupérer le label ou l'id du block
        
        $cmsManager = $this->get('kitpages.cms.model.cmsManager');
        $em = $this->getDoctrine()->getEntityManager();
        
        $resultingHtml = '';
        if ($cmsManager->getViewMode() == CmsManager::VIEW_MODE_EDIT) {
            $block = $em->getRepository('KitpagesCmsBundle:Block')->findOneBy(array('slug' => $label));
            if ($block->getBlockType() == Block::BLOCK_TYPE_EDITO) {
                $dataRenderer = $this->container->getParameter('kitpages_cms.block.renderer.'.$block->getTemplate());
                $resultingHtml = $this->renderView($dataRenderer['default']['twig'], array('data' => $block->getData()));
            }
        } elseif ($cmsManager->getViewMode() == CmsManager::VIEW_MODE_PREVIEW) {
            $block = $em->getRepository('KitpagesCmsBundle:Block')->findOneBy(array('slug' => $label));
            if ($block->getBlockType() == Block::BLOCK_TYPE_EDITO) {
                $dataRenderer = $this->container->getParameter('kitpages_cms.block.renderer.'.$block->getTemplate());
                $resultingHtml = $this->renderView($dataRenderer['default']['twig'], array('data' => $block->getData()));
            }          
        } elseif ($cmsManager->getViewMode() == CmsManager::VIEW_MODE_PROD) {
            $blockPublish = $em->getRepository('KitpagesCmsBundle:BlockPublish')->findOneBy(array('slug' => $label));
            if (!is_null($blockPublish)) {
                $data = $blockPublish->getData();
                if ($blockPublish->getBlockType() == Block::BLOCK_TYPE_EDITO) {
                    $resultingHtml = $data['html'];
                }
            }
        }
       
        return new Response($resultingHtml);
    }
    
    public function editSuccessAction()
    {
        return $this->render('KitpagesCmsBundle:Block:edit-success.html.twig');
    }
    
    public function publishAction(Block $block)
    {
        $blockManager = $this->get('kitpages.cms.manager.block');
        $dataRenderer = $this->container->getParameter('kitpages_cms.block.renderer.'.$block->getTemplate());
        $blockManager->publish($block, $dataRenderer);
        return $this->render('KitpagesCmsBundle:Block:publish.html.twig');
    }
    //pas de sens uniquement pour les tests
    public function unpublishAction(Block $block)
    {
        $block->setIsPublished(false);
        $em = $this->getDoctrine()->getEntityManager();
        $em->persist($block);
        $em->flush();
        return $this->render('KitpagesCmsBundle:Block:publish.html.twig');
    }

}
