<?php

/*
 * This file is part of the SGLFLTSBundle package.
 *
 * (c) Simon Guillem-Lessard <s.g.lessard@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SGL\FLTSBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use SGL\FLTSBundle\Entity\Work;
use SGL\FLTSBundle\Form\WorkType;

use Symfony\Component\HttpFoundation\Response;

/**
 * Work controller.
 *
 * @Route("/")
 */
class WorkController extends Controller
{
    /**
     * Lists all Work entities.
     *
     * @Route("/{id_project}/{id_part}/{id_task}", requirements={"id_project" = "\d+", "id_part" = "\d+", "id_task" = "\d+"}, name="sgl_flts_work")
     * @Template("SGLFLTSBundle:Work:List/index.html.twig")
     */
    public function indexAction($id_project,$id_part,$id_task)
    {
        $em = $this->getDoctrine()->getManager();

        $task = $em->getRepository('SGLFLTSBundle:Task')->find($id_task);

        if (!$task) {
            throw $this->createNotFoundException('Unable to find Task entity.');
        }

        $entities = $task->getWorks();

        return array(
            'entities' => $entities,
            'task'     => $task,
            'part'     => $task->getPart(),
            'project'  => $task->getPart()->getProject(),
        );
    }

    /**
     * Finds and displays a Work entity.
     *
     * @Route("/{id_project}/{id_part}/{id_task}/{id}/show", name="sgl_flts_work_show")
     * @Template("SGLFLTSBundle:work:Crud/show.html.twig")
     */
    public function showAction($id_project,$id_part,$id_task,$id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('SGLFLTSBundle:Work')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Work entity.');
        }

        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity'      => $entity,
            'task'        => $entity->getTask(),
            'part'        => $entity->getTask()->getPart(),
            'project'     => $entity->getTask()->getPart()->getProject(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Displays a form to create a new Work entity.
     *
     * @Route("/{id_project}/{id_part}/{id_task}/new", name="sgl_flts_work_new")
     * @Template("SGLFLTSBundle:work:Crud/new.html.twig")
     */
    public function newAction($id_project,$id_part,$id_task)
    {
        $em = $this->getDoctrine()->getManager();

        $task = $em->getRepository('SGLFLTSBundle:Task')->find($id_task);
        $part = $em->getRepository('SGLFLTSBundle:Part')->find($id_part);

        if (!$part) {
            throw $this->createNotFoundException('Unable to find Part entity.');
        }

        $latest_part_work = $part->getLastWork();
        if (!$latest_part_work) {
            // Dummy work : now
            $latest_part_work = $em->getRepository('SGLFLTSBundle:Work')->retrieveDummyPartWork($part);
        }
        $latest_part_work_ended_at = new \DateTime($latest_part_work->getEndedAt()->format('Y-m-d H:i:s'));

        if (!$task) {
            // Get first task
            $task = $part->getFirstRankTask();

            if (!$task) {
                throw $this->createNotFoundException('Unable to find any Task entity.');
            }
        }

        $entity = new Work();
        $entity->setUser($this->getUser());
        $entity->setTask($task);
        $entity->setWorkedAt($latest_part_work->getWorkedAt());                     // Default = last work day
        $entity->setStartedAt($latest_part_work_ended_at);                          // Default = last work ended_at
        $default_ended_at = clone $latest_part_work_ended_at;                       // Default = last work ended_at + 12 minutes
        $entity->setEndedAt($default_ended_at->add(new \DateInterval('PT12M')));

        if ($rate = $part->getProject()->getClient()->getRate()) {
            $entity->setRate($rate);
        }

        $form   = $this->createForm(new WorkType(), $entity, array('part'=>$part));

        return array(
            'entity'      => $entity,
            'project'     => $part->getProject(),
            'part'        => $part,
            'task'        => $task,
            'form'        => $form->createView(),
        );
    }

    /**
     * Creates a new Work entity.
     *
     * @Route("/create", name="sgl_flts_work_create")
     * @Method("POST")
     * @Template("SGLFLTSBundle:work:Crud/new.html.twig")
     */
    public function createAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $work_type = new WorkType();

        $post_val = $request->get($work_type->getName());

        $task = $em->getRepository('SGLFLTSBundle:Task')->find($post_val['task']);
        unset($post_val);

        if (!$task) {
            throw $this->createNotFoundException('Unable to find Task entity.');
        }

        $entity  = new Work();
        $form = $this->createForm($work_type, $entity, array('part'=>$task->getPart()));
        $form->bind($request);

        if ($form->isValid()) {

            $em->persist($entity);
            $em->flush();

            $task = $entity->getTask();

            if ($request->request->get('submit-work-create-add')) {
                return $this->redirect($this->generateUrl('sgl_flts_work_new', array('id_project'=>$task->getPart()->getProject()->getId(), 'id_part'=>$task->getPart()->getId(), 'id_task'=>$task->getId())));
            } else if ($request->request->get('submit-work-create-show')) {
                return $this->redirect($this->generateUrl('sgl_flts_work_show', array('id' => $entity->getId(),'id_project'=>$task->getPart()->getProject()->getId(), 'id_part'=>$task->getPart()->getId(), 'id_task'=>$task->getId())));
            } else {
                return $this->redirect($this->generateUrl('sgl_flts_work_edit', array('id' => $entity->getId(), 'id_project'=>$task->getPart()->getProject()->getId(), 'id_part'=>$task->getPart()->getId(), 'id_task'=>$task->getId())));
            }
        }

        return array(
            'entity'   => $entity,
            'project'  => $task->getPart()->getProject(),
            'part'     => $task->getPart(),
            'task'     => $task,
            'form'     => $form->createView(),
        );
    }

    /**
     * Displays a form to edit an existing Work entity.
     *
     * @Route("/{id_project}/{id_part}/{id_task}/{id}/edit", name="sgl_flts_work_edit")
     * @Template("SGLFLTSBundle:work:Crud/edit.html.twig")
     */
    public function editAction($id_project,$id_part,$id_task,$id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('SGLFLTSBundle:Work')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Work entity.');
        }

        $task = $em->getRepository('SGLFLTSBundle:Task')->find($id_task);

        if (!$task) {
            throw $this->createNotFoundException('Unable to find Task entity.');
        }

        $editForm = $this->createForm(new WorkType(), $entity, array('part'=>$task->getPart()));
        $deleteForm = $this->createDeleteForm($id);

        return array(
            'entity'      => $entity,
            'project'     => $task->getPart()->getProject(),
            'part'        => $task->getPart(),
            'task'        => $task,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Edits an existing Work entity.
     *
     * @Route("/{id}/update", name="sgl_flts_work_update")
     * @Method("POST")
     * @Template("SGLFLTSBundle:work:Crud/edit.html.twig")
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $work_type = new WorkType();

        $entity = $em->getRepository('SGLFLTSBundle:Work')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Work entity.');
        }

        $post_val = $request->get($work_type->getName());

        $task = $em->getRepository('SGLFLTSBundle:Task')->find($post_val['task']);
        unset($post_val);

        if (!$task) {
            throw $this->createNotFoundException('Unable to find Task entity.');
        }

        $deleteForm = $this->createDeleteForm($id);
        $editForm = $this->createForm($work_type, $entity, array('part'=>$task->getPart()));
        $editForm->bind($request);

        if ($editForm->isValid()) {
            $em->persist($entity);
            $em->flush();

            $task = $entity->getTask();

            if ($request->request->get('submit-work-edit-add')) {
                return $this->redirect($this->generateUrl('sgl_flts_work_new', array('id_project'=>$task->getPart()->getProject()->getId(), 'id_part'=>$task->getPart()->getId(), 'id_task'=>$task->getId())));
            } else if ($request->request->get('submit-work-edit-show')) {
                return $this->redirect($this->generateUrl('sgl_flts_work_show', array('id' => $id,'id_project'=>$task->getPart()->getProject()->getId(), 'id_part'=>$task->getPart()->getId(), 'id_task'=>$task->getId())));
            } else {
                return $this->redirect($this->generateUrl('sgl_flts_work_edit', array('id' => $id, 'id_project'=>$task->getPart()->getProject()->getId(), 'id_part'=>$task->getPart()->getId(), 'id_task'=>$task->getId())));
            }


        }

        return array(
            'entity'      => $entity,
            'project'     => $task->getPart()->getProject(),
            'part'        => $task->getPart(),
            'task'        => $task,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    /**
     * Bill a job.
     *
     * @Route("/{id}/bill/{id_bill}", name="sgl_flts_work_bill")
     * @Method("POST")
     */
    public function billToAction($id,$id_bill) {

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('SGLFLTSBundle:Work')->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Work entity.');
        }

        $bill = $em->getRepository('SGLFLTSBundle:Bill')->find($id_bill);
        if (!$bill) {
            throw $this->createNotFoundException('Unable to find Bill entity.');
        }

        if ($this->getRequest()->request->get('checked',false) == 'true') {
            $entity->setBill($bill);
        } else {
            $entity->setBill(null);
        }

        $em->persist($entity);
        $em->flush();

        $response = new Response(json_encode(array(
            'id_bill' => $bill->getId(),
            'id_work' => $entity->getId(),
        )));
        return $response;
    }
    /**
     * Deletes a Work entity.
     *
     * @Route("/{id}/delete", name="sgl_flts_work_delete")
     * @Method("POST")
     */
    public function deleteAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        $form = $this->createDeleteForm($id);
        $form->bind($request);

        $entity = $em->getRepository('SGLFLTSBundle:Work')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Work entity.');
        }

        // Can't delete Work if bill is sent
        if ($entity->getSent()) {
            throw new HttpException(403,'Forbidden : Cannot delete work since the bill is sent.');
        }

        $task = $entity->getTask();

        if ($form->isValid()) {
            $em->remove($entity);
            $em->flush();
        }

        return $this->redirect($this->generateUrl('sgl_flts_work',array('id_project'=>$task->getPart()->getProject()->getId(), 'id_part'=>$task->getPart()->getId(), 'id_task'=>$task->getId())));
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->getForm()
        ;
    }
}
