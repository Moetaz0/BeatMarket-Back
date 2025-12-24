<?php

namespace App\Controller\Api;

use App\Entity\Beat;
use App\Entity\Beatmaker;
use App\Entity\License;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/beats', name: 'api_beats_')]
class BeatController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * GET /api/beats - Get all beats with optional filters
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = (int) ($request->query->get('page', 1));
        $limit = (int) ($request->query->get('limit', 10));
        $offset = ($page - 1) * $limit;

        $beatRepo = $this->em->getRepository(Beat::class);
        $beats = $beatRepo->findBy([], ['uploadedAt' => 'DESC'], $limit, $offset);
        $total = count($beatRepo->findAll());

        $data = [];
        foreach ($beats as $beat) {
            $data[] = $this->beatToArray($beat);
        }

        return $this->json([
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * GET /api/beats/{id} - Get a specific beat by ID
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $beat = $this->em->getRepository(Beat::class)->find($id);

        if (!$beat) {
            return $this->json(['error' => 'Beat not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->beatToArray($beat));
    }

    /**
     * POST /api/beats - Create a new beat
     * 
     * Request body example:
     * {
     *   "title": "Summer Vibes",
     *   "description": "Chill beat with tropical vibes",
     *   "fileUrl": "https://example.com/beat.mp3",
     *   "coverImage": "https://example.com/cover.jpg",
     *   "price": 29.99,
     *   "genre": "Hip-Hop",
     *   "bpm": 95,
     *   "key": "C#m",
     *   "beatmakerId": 1,
     *   "licenseId": 2
     * }
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $required = ['title', 'fileUrl', 'price', 'genre', 'bpm', 'beatmakerId'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->json(
                    ['error' => "Missing required field: $field"],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        // Get the beatmaker
        $beatmaker = $this->em->getRepository(Beatmaker::class)->find($data['beatmakerId']);
        if (!$beatmaker) {
            return $this->json(
                ['error' => 'Beatmaker not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Get the license (optional)
        $license = null;
        if (isset($data['licenseId'])) {
            $license = $this->em->getRepository(License::class)->find($data['licenseId']);
            if (!$license) {
                return $this->json(
                    ['error' => 'License not found'],
                    Response::HTTP_NOT_FOUND
                );
            }
        }

        // Create the beat
        $beat = new Beat();
        $beat->setTitle($data['title']);
        $beat->setFileUrl($data['fileUrl']);
        $beat->setPrice($data['price']);
        $beat->setGenre($data['genre']);
        $beat->setBpm($data['bpm']);
        $beat->setBeatmaker($beatmaker);

        // Optional fields
        if (isset($data['description'])) {
            $beat->setDescription($data['description']);
        }
        if (isset($data['coverImage'])) {
            $beat->setCoverImage($data['coverImage']);
        }
        if (isset($data['key'])) {
            $beat->setKey($data['key']);
        }
        if ($license) {
            $beat->setLicense($license);
        }

        $this->em->persist($beat);
        $this->em->flush();

        return $this->json(
            $this->beatToArray($beat),
            Response::HTTP_CREATED
        );
    }

    /**
     * PUT /api/beats/{id} - Update a beat
     * 
     * Request body example (all fields optional):
     * {
     *   "title": "Updated Title",
     *   "price": 39.99,
     *   "licenseId": 3
     * }
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $beat = $this->em->getRepository(Beat::class)->find($id);

        if (!$beat) {
            return $this->json(['error' => 'Beat not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Update optional fields
        if (isset($data['title'])) {
            $beat->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $beat->setDescription($data['description']);
        }
        if (isset($data['price'])) {
            $beat->setPrice($data['price']);
        }
        if (isset($data['genre'])) {
            $beat->setGenre($data['genre']);
        }
        if (isset($data['bpm'])) {
            $beat->setBpm($data['bpm']);
        }
        if (isset($data['key'])) {
            $beat->setKey($data['key']);
        }
        if (isset($data['fileUrl'])) {
            $beat->setFileUrl($data['fileUrl']);
        }
        if (isset($data['coverImage'])) {
            $beat->setCoverImage($data['coverImage']);
        }

        // Update license
        if (isset($data['licenseId'])) {
            if ($data['licenseId'] === null) {
                $beat->setLicense(null);
            } else {
                $license = $this->em->getRepository(License::class)->find($data['licenseId']);
                if (!$license) {
                    return $this->json(
                        ['error' => 'License not found'],
                        Response::HTTP_NOT_FOUND
                    );
                }
                $beat->setLicense($license);
            }
        }

        $this->em->flush();

        return $this->json($this->beatToArray($beat));
    }

    /**
     * DELETE /api/beats/{id} - Delete a beat
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $beat = $this->em->getRepository(Beat::class)->find($id);

        if (!$beat) {
            return $this->json(['error' => 'Beat not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($beat);
        $this->em->flush();

        return $this->json(['message' => 'Beat deleted successfully']);
    }

    /**
     * Convert Beat entity to array for JSON response
     */
    private function beatToArray(Beat $beat): array
    {
        return [
            'id' => $beat->getId(),
            'title' => $beat->getTitle(),
            'description' => $beat->getDescription(),
            'fileUrl' => $beat->getFileUrl(),
            'coverImage' => $beat->getCoverImage(),
            'price' => $beat->getPrice(),
            'genre' => $beat->getGenre(),
            'bpm' => $beat->getBpm(),
            'key' => $beat->getKey(),
            'uploadedAt' => $beat->getUploadedAt()->format('Y-m-d H:i:s'),
            'beatmaker' => $beat->getBeatmaker() ? [
                'id' => $beat->getBeatmaker()->getId(),
                'username' => $beat->getBeatmaker()->getUsername(),
            ] : null,
            'license' => $beat->getLicense() ? [
                'id' => $beat->getLicense()->getId(),
                'name' => $beat->getLicense()->getName(),
                'priceMultiplier' => $beat->getLicense()->getPriceMultiplier(),
            ] : null,
        ];
    }
}
