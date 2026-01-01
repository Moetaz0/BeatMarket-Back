# Test Profile Endpoint

## 1. First, login to get a token:

```bash
curl -X POST https://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"your-email@example.com","password":"yourpassword"}'
```

## 2. Copy the token from response, then test profile:

```bash
curl https://127.0.0.1:8000/api/user/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

If step 2 returns profile data, the backend works - issue is in frontend.
If step 2 returns 401, check token validity or backend config.
