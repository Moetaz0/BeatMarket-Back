<?php

namespace App\Controller\Api;

use App\Entity\Beat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/feed', name: 'api_feed_')]
class FeedController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * GET /api/feed/recent - Get recently uploaded beats
     */
    #[Route('/recent', name: 'recent', methods: ['GET'])]
    public function recent(Request $request): JsonResponse
    {
        $limit = (int) ($request->query->get('limit', 20));
        $offset = (int) ($request->query->get('offset', 0));

        $beatRepo = $this->em->getRepository(Beat::class);
        $beats = $beatRepo->findBy(
            [],
            ['uploadedAt' => 'DESC'],
            $limit,
            $offset
        );

        $data = [];
        foreach ($beats as $beat) {
            $data[] = $this->beatToArray($beat);
        }

        return $this->json([
            'data' => $data,
            'meta' => [
                'count' => count($data),
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
    }

    /**
     * GET /api/feed/trending - Get trending beats
     * (Based on recent uploads with high engagement - for now, sorted by upload date)
     * TODO: Implement actual trending algorithm based on plays, purchases, etc.
     */
    #[Route('/trending', name: 'trending', methods: ['GET'])]
    public function trending(Request $request): JsonResponse
    {
        $limit = (int) ($request->query->get('limit', 20));
        $offset = (int) ($request->query->get('offset', 0));

        // For now, get beats from the last 30 days, sorted by upload date
        // In a real implementation, you'd want to track plays, purchases, likes, etc.
        $thirtyDaysAgo = new \DateTime('-30 days');

        $qb = $this->em->createQueryBuilder();
        $qb->select('b')
            ->from(Beat::class, 'b')
            ->where('b.uploadedAt >= :thirtyDaysAgo')
            ->setParameter('thirtyDaysAgo', $thirtyDaysAgo)
            ->orderBy('b.uploadedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $beats = $qb->getQuery()->getResult();

        $data = [];
        foreach ($beats as $beat) {
            $data[] = $this->beatToArray($beat);
        }

        return $this->json([
            'data' => $data,
            'meta' => [
                'count' => count($data),
                'limit' => $limit,
                'offset' => $offset,
                'period' => 'last_30_days'
            ]
        ]);
    }

    /**
     * GET /api/feed/genres - Get list of available genres with beat count
     */
    #[Route('/genres', name: 'genres', methods: ['GET'])]
    public function genres(): JsonResponse
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('b.genre, COUNT(b.id) as beatCount')
            ->from(Beat::class, 'b')
            ->groupBy('b.genre')
            ->orderBy('beatCount', 'DESC');

        $results = $qb->getQuery()->getResult();

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'genre' => $result['genre'],
                'beatCount' => (int) $result['beatCount']
            ];
        }

        return $this->json([
            'data' => $data,
            'meta' => [
                'total_genres' => count($data)
            ]
        ]);
    }

    /**
     * GET /api/feed/by-genre/{genre} - Get beats by specific genre
     */
    #[Route('/by-genre/{genre}', name: 'by_genre', methods: ['GET'])]
    public function byGenre(string $genre, Request $request): JsonResponse
    {
        $limit = (int) ($request->query->get('limit', 20));
        $offset = (int) ($request->query->get('offset', 0));

        $beatRepo = $this->em->getRepository(Beat::class);
        $beats = $beatRepo->findBy(
            ['genre' => $genre],
            ['uploadedAt' => 'DESC'],
            $limit,
            $offset
        );

        if (empty($beats)) {
            return $this->json([
                'error' => 'No beats found for this genre'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = [];
        foreach ($beats as $beat) {
            $data[] = $this->beatToArray($beat);
        }

        return $this->json([
            'data' => $data,
            'meta' => [
                'genre' => $genre,
                'count' => count($data),
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
    }

    /**
     * Helper method to convert Beat entity to array
     */
    private function beatToArray(Beat $beat): array
    {
        $user = $beat->getUser();
        $license = $beat->getLicense();

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
            'user' => $user ? [
                'id' => $user->getId(),
                'username' => $user->getUsername() ?? 'Unknown',
                'email' => $user->getEmail() ?? 'Unknown'
            ] : null,
            'license' => $license ? [
                'id' => $license->getId(),
                'name' => $license->getName(),
                'price' => $license->getPrice()
            ] : null
        ];
    }
}
