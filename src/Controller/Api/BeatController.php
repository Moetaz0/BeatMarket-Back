<?php

namespace App\Controller\Api;

use App\Entity\Beat;
use App\Entity\User;
use App\Entity\License;
use App\Service\CloudinaryService;
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
    private CloudinaryService $cloudinaryService;

    public function __construct(
        EntityManagerInterface $em,
        CloudinaryService $cloudinaryService
    ) {
        $this->em = $em;
        $this->cloudinaryService = $cloudinaryService;
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
     * Request body (multipart/form-data):
     * - audioFile: (file) The audio file to upload (required)
     * - title: (string) Beat title (required)
     * - price: (float) Beat price (required)
     * - genre: (string) Beat genre (required)
     * - bpm: (int) Beats per minute (required)
     * - userId: (int) User ID (required)
     * - description: (string) Beat description (optional)
     * - coverImage: (file) Cover image file (optional)
     * - key: (string) Musical key (optional)
     * - licenseId: (int) License ID (optional)
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Get form data
        $title = $request->request->get('title');
        $price = $request->request->get('price');
        $genre = $request->request->get('genre');
        $bpm = $request->request->get('bpm');
        $userId = $request->request->get('userId');
        $description = $request->request->get('description');
        $key = $request->request->get('key');
        $licenseId = $request->request->get('licenseId');

        // Get uploaded files
        $audioFile = $request->files->get('audioFile');
        $coverImageFile = $request->files->get('coverImage');

        // Validate required fields
        if (!$title || !$price || !$genre || !$bpm || !$userId) {
            return $this->json(
                ['error' => 'Missing required fields: title, price, genre, bpm, userId'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Validate audio file
        if (!$audioFile) {
            return $this->json(
                ['error' => 'Audio file is required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Validate audio file type
        $allowedAudioTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg'];
        if (!in_array($audioFile->getMimeType(), $allowedAudioTypes)) {
            return $this->json(
                ['error' => 'Invalid audio file type. Allowed types: MP3, WAV, OGG'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Get the user
        $user = $this->em->getRepository(User::class)->find($userId);
        if (!$user) {
            return $this->json(
                ['error' => 'User not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Get the license (optional)
        $license = null;
        if ($licenseId) {
            $license = $this->em->getRepository(License::class)->find($licenseId);
            if (!$license) {
                return $this->json(
                    ['error' => 'License not found'],
                    Response::HTTP_NOT_FOUND
                );
            }
        }

        // Upload audio file to Cloudinary
        $audioUrl = $this->cloudinaryService->upload(
            $audioFile,
            'beatmarket/beats/audio',
            null
        );

        if (!$audioUrl) {
            return $this->json(
                ['error' => 'Failed to upload audio file'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // Upload cover image if provided
        $coverImageUrl = null;
        if ($coverImageFile) {
            $coverImageUrl = $this->cloudinaryService->upload(
                $coverImageFile,
                'beatmarket/beats/covers',
                null
            );
        }

        // Create the beat
        $beat = new Beat();
        $beat->setTitle($title);
        $beat->setFileUrl($audioUrl);
        $beat->setPrice((float) $price);
        $beat->setGenre($genre);
        $beat->setBpm((int) $bpm);
        $beat->setUser($user);

        // Optional fields
        if ($description) {
            $beat->setDescription($description);
        }
        if ($coverImageUrl) {
            $beat->setCoverImage($coverImageUrl);
        }
        if ($key) {
            $beat->setKey($key);
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
            'user' => $beat->getUser() ? [
                'id' => $beat->getUser()->getId(),
                'username' => $beat->getUser()->getUsername(),
            ] : null,
            'license' => $beat->getLicense() ? [
                'id' => $beat->getLicense()->getId(),
                'name' => $beat->getLicense()->getName(),
                'priceMultiplier' => $beat->getLicense()->getPriceMultiplier(),
            ] : null,
        ];
    }

    /**
     * Additional methods for handling beat-related functionalities can be added here
     */

    #[Route('/user/{userId}', name: 'list_by_user', methods: ['GET'])]
    public function listByUser(int $userId, Request $request): JsonResponse
    {
        $page = (int) ($request->query->get('page', 1));
        $limit = (int) ($request->query->get('limit', 10));
        $offset = ($page - 1) * $limit;

        $beatRepo = $this->em->getRepository(Beat::class);
        $beats = $beatRepo->findBy(['user' => $userId], ['uploadedAt' => 'DESC'], $limit, $offset);
        $total = count($beatRepo->findBy(['user' => $userId]));

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


}
