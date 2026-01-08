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
     * GET /api/beats - Get all beats with optional filters (excludes exclusive beats)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = (int) ($request->query->get('page', 1));
        $limit = (int) ($request->query->get('limit', 10));
        $offset = ($page - 1) * $limit;

        $beatRepo = $this->em->getRepository(Beat::class);
        // Get all non-exclusive beats
        $beats = $beatRepo->findBy(['isExclusive' => false], ['uploadedAt' => 'DESC'], $limit, $offset);
        $total = count($beatRepo->findBy(['isExclusive' => false]));

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
     * - key: (string) Musical key (optional)
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

        // Get uploaded files
        $audioFile = $request->files->get('audioFile');

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
        if ($key) {
            $beat->setKey($key);
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
     * Request body (multipart/form-data):
     * - title: (string) Beat title (optional)
     * - price: (float) Beat price (optional)
     * - genre: (string) Beat genre (optional)
     * - bpm: (int) Beats per minute (optional)
     * - description: (string) Beat description (optional)
     * - key: (string) Musical key (optional)
     * - licenseId: (int) License ID (optional)
     * - audioFile: (file) New audio file (optional)
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $beat = $this->em->getRepository(Beat::class)->find($id);

        if (!$beat) {
            return $this->json(['error' => 'Beat not found'], Response::HTTP_NOT_FOUND);
        }

        // Get form data
        $title = $request->request->get('title');
        $price = $request->request->get('price');
        $genre = $request->request->get('genre');
        $bpm = $request->request->get('bpm');
        $description = $request->request->get('description');
        $key = $request->request->get('key');
        $licenseId = $request->request->get('licenseId');

        // Get uploaded files
        $audioFile = $request->files->get('audioFile');

        // Update text fields if provided
        if ($title) {
            $beat->setTitle($title);
        }
        if ($description !== null) {
            $beat->setDescription($description);
        }
        if ($price !== null) {
            $beat->setPrice((float) $price);
        }
        if ($genre) {
            $beat->setGenre($genre);
        }
        if ($bpm !== null) {
            $beat->setBpm((int) $bpm);
        }
        if ($key !== null) {
            $beat->setKey($key);
        }

        // Update audio file if provided
        if ($audioFile) {
            // Validate audio file type
            $allowedAudioTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg'];
            if (!in_array($audioFile->getMimeType(), $allowedAudioTypes)) {
                return $this->json(
                    ['error' => 'Invalid audio file type. Allowed types: MP3, WAV, OGG'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Delete old audio file from Cloudinary if exists
            if ($beat->getFileUrl()) {
                $this->cloudinaryService->delete($beat->getFileUrl());
            }

            // Upload new audio file
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

            $beat->setFileUrl($audioUrl);
        }

        // Update license
        if ($licenseId !== null) {
            if ($licenseId === '' || $licenseId === '0') {
                $beat->setLicense(null);
            } else {
                $license = $this->em->getRepository(License::class)->find($licenseId);
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
     * POST /api/beats/{id}/cover-image - Upload or update cover image for a beat
     * 
     * Request body (multipart/form-data):
     * - coverImage: (file) Cover image file (required)
     */
    #[Route('/{id}/cover-image', name: 'upload_cover_image', methods: ['POST'])]
    public function uploadCoverImage(int $id, Request $request): JsonResponse
    {
        $beat = $this->em->getRepository(Beat::class)->find($id);

        if (!$beat) {
            return $this->json(['error' => 'Beat not found'], Response::HTTP_NOT_FOUND);
        }

        $coverImageFile = $request->files->get('coverImage');

        if (!$coverImageFile) {
            return $this->json(
                ['error' => 'Cover image file is required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Validate image file type
        $allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($coverImageFile->getMimeType(), $allowedImageTypes)) {
            return $this->json(
                ['error' => 'Invalid image file type. Allowed types: JPEG, PNG, GIF, WEBP'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Delete old cover image from Cloudinary if exists
        if ($beat->getCoverImage()) {
            $this->cloudinaryService->delete($beat->getCoverImage());
        }

        // Upload new cover image
        $coverImageUrl = $this->cloudinaryService->upload(
            $coverImageFile,
            'beatmarket/beats/covers',
            null
        );

        if (!$coverImageUrl) {
            return $this->json(
                ['error' => 'Failed to upload cover image'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $beat->setCoverImage($coverImageUrl);
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
            'views' => $beat->getViews(),
            'uploadedAt' => $beat->getUploadedAt()->format('Y-m-d H:i:s'),
            'isExclusive' => $beat->isExclusive(),
            'exclusiveOwner' => $beat->getExclusiveOwner() ? [
                'id' => $beat->getExclusiveOwner()->getId(),
                'username' => $beat->getExclusiveOwner()->getUsername(),
            ] : null,
            'user' => $beat->getUser() ? [
                'id' => $beat->getUser()->getId(),
                'username' => $beat->getUser()->getUsername(),
            ] : null,
            'license' => $beat->getLicense() ? [
                'id' => $beat->getLicense()->getId(),
                'name' => $beat->getLicense()->getName(),
                'priceMultiplier' => $beat->getLicense()->getPriceMultiplier(),
                'isExclusive' => $beat->getLicense()->isExclusive(),
            ] : null,
        ];
    }

    /**
     * Additional methods for handling beat-related functionalities can be added here
     */

    /**
     * GET /api/beats/{id}/views - Get view count for a specific beat
     */
    #[Route('/{id}/views', name: 'get_views', methods: ['GET'])]
    public function getViews(int $id): JsonResponse
    {
        $beat = $this->em->getRepository(Beat::class)->find($id);

        if (!$beat) {
            return $this->json(['error' => 'Beat not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $beat->getId(),
            'title' => $beat->getTitle(),
            'views' => $beat->getViews(),
        ]);
    }

    /**
     * POST /api/beats/{id}/play - Increment view count when beat is played
     */
    #[Route('/{id}/play', name: 'play', methods: ['POST'])]
    public function play(int $id): JsonResponse
    {
        $beat = $this->em->getRepository(Beat::class)->find($id);

        if (!$beat) {
            return $this->json(['error' => 'Beat not found'], Response::HTTP_NOT_FOUND);
        }

        // Increment the views count
        $beat->incrementViews();
        $this->em->persist($beat);
        $this->em->flush();

        return $this->json([
            'message' => 'View counted successfully',
            'beat' => $this->beatToArray($beat)
        ]);
    }

    /**
     * GET /api/beats/popular - Get popular beats sorted by views (excludes exclusive beats)
     */
    #[Route('/popular', name: 'popular', methods: ['GET'])]
    public function popular(Request $request): JsonResponse
    {
        $page = (int) ($request->query->get('page', 1));
        $limit = (int) ($request->query->get('limit', 10));
        $offset = ($page - 1) * $limit;

        $beatRepo = $this->em->getRepository(Beat::class);
        $beats = $beatRepo->findBy(['isExclusive' => false], ['views' => 'DESC'], $limit, $offset);
        $total = count($beatRepo->findBy(['isExclusive' => false]));

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
     * GET /api/beats/trending - Get trending beats, most viewed (excludes exclusive beats)
     */
    #[Route('/trending', name: 'trending', methods: ['GET'])]
    public function trending(Request $request): JsonResponse
    {
        $page = (int) ($request->query->get('page', 1));
        $limit = (int) ($request->query->get('limit', 10));
        $offset = ($page - 1) * $limit;

        $beatRepo = $this->em->getRepository(Beat::class);
        $beats = $beatRepo->findBy(['isExclusive' => false], ['views' => 'DESC'], $limit, $offset);
        $total = count($beatRepo->findBy(['isExclusive' => false]));

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
