<?php

namespace App\Controller;

use App\Dto\Form\NewDatabase;
use App\Entity\User;
use App\Service\DatabaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, DatabaseService $databaseService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('indieauth');
        }

        $newDatabase = new NewDatabase();
        $form = $this->createFormBuilder($newDatabase)
            ->add('databaseName', TextType::class, ['label' => 'Database:'])
            ->add('save', SubmitType::class, ['label' => 'Save'])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $databaseService->createDatabase($newDatabase, $user);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_home');
        }

        $databases = $databaseService->getUserDatabases($user);

        return $this->render('home/index.html.twig', [
            'databases' => $databases,
            'form' => $form->createView(),
        ]);
    }
}
