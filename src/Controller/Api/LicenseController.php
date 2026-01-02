<?php

namespace App\Controller\Api;

use App\Entity\License;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/licenses', name: 'api_licenses_')]
class LicenseController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * GET /api/licenses - Get all licenses
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $licenses = $this->em->getRepository(License::class)->findAll();

        $data = [];
        foreach ($licenses as $license) {
            $data[] = $this->licenseToArray($license);
        }

        return $this->json([
            'data' => $data,
            'total' => count($data)
        ]);
    }

    /**
     * GET /api/licenses/{id} - Get a specific license by ID
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $license = $this->em->getRepository(License::class)->find($id);

        if (!$license) {
            return $this->json(['error' => 'License not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->licenseToArray($license));
    }

    /**
     * POST /api/licenses - Create a new license
     * 
     * Request body example:
     * {
     *   "name": "Premium License",
     *   "terms": "Can be used for premium projects",
     *   "priceMultiplier": 2.0
     * }
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['name']) || !isset($data['priceMultiplier'])) {
            return $this->json(
                ['error' => 'name and priceMultiplier are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Validate data types
        if (!is_string($data['name']) || !is_numeric($data['priceMultiplier'])) {
            return $this->json(
                ['error' => 'name must be string and priceMultiplier must be a number'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (empty($data['name'])) {
            return $this->json(
                ['error' => 'name cannot be empty'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $priceMultiplier = (float) $data['priceMultiplier'];
        if ($priceMultiplier <= 0) {
            return $this->json(
                ['error' => 'priceMultiplier must be greater than 0'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Create the license
        $license = new License();
        $license->setName(trim($data['name']));
        $license->setPriceMultiplier($priceMultiplier);

        // Optional field
        if (isset($data['terms']) && !empty($data['terms'])) {
            $license->setTerms($data['terms']);
        }

        $this->em->persist($license);
        $this->em->flush();

        return $this->json(
            $this->licenseToArray($license),
            Response::HTTP_CREATED
        );
    }

    /**
     * PUT /api/licenses/{id} - Update a license
     * 
     * Request body example (all fields optional):
     * {
     *   "name": "Premium License",
     *   "terms": "Updated terms",
     *   "priceMultiplier": 2.0
     * }
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $license = $this->em->getRepository(License::class)->find($id);

        if (!$license) {
            return $this->json(['error' => 'License not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Update optional fields
        if (isset($data['name'])) {
            if (!is_string($data['name']) || empty($data['name'])) {
                return $this->json(
                    ['error' => 'name must be a non-empty string'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            $license->setName(trim($data['name']));
        }

        if (isset($data['priceMultiplier'])) {
            if (!is_numeric($data['priceMultiplier'])) {
                return $this->json(
                    ['error' => 'priceMultiplier must be a number'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            $priceMultiplier = (float) $data['priceMultiplier'];
            if ($priceMultiplier <= 0) {
                return $this->json(
                    ['error' => 'priceMultiplier must be greater than 0'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            $license->setPriceMultiplier($priceMultiplier);
        }

        if (isset($data['terms'])) {
            $license->setTerms($data['terms'] ?? null);
        }

        $this->em->flush();

        return $this->json($this->licenseToArray($license));
    }

    /**
     * DELETE /api/licenses/{id} - Delete a license
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $license = $this->em->getRepository(License::class)->find($id);

        if (!$license) {
            return $this->json(['error' => 'License not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($license);
        $this->em->flush();

        return $this->json(['message' => 'License deleted successfully']);
    }

    /**
     * Convert License entity to array for JSON response
     */
    private function licenseToArray(License $license): array
    {
        return [
            'id' => $license->getId(),
            'name' => $license->getName(),
            'terms' => $license->getTerms(),
            'priceMultiplier' => $license->getPriceMultiplier(),
        ];
    }
}
