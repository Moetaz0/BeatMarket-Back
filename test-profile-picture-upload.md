# Profile Picture Upload - API Documentation

## Overview

Upload and manage user profile pictures with Cloudinary integration. The profile picture URL is stored in the database.

## Endpoints

### 1. Upload Profile Picture

**POST** `/api/user/profile-picture`

Upload a profile picture to Cloudinary and store the URL in the database.

**Headers:**

```
Authorization: Bearer {your-jwt-token}
Content-Type: multipart/form-data
```

**Request Body:**

-   Form field: `file` (the image file)

**Allowed formats:** JPEG, JPG, PNG, GIF, WEBP
**Max size:** 5MB

**Example using cURL:**

```bash
curl -X POST http://localhost:8000/api/user/profile-picture \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@/path/to/your/image.jpg"
```

**Example using Postman:**

1. Set method to POST
2. URL: `http://localhost:8000/api/user/profile-picture`
3. Headers: `Authorization: Bearer YOUR_JWT_TOKEN`
4. Body:
    - Select "form-data"
    - Key: `file` (change type to "File")
    - Value: Select your image file

**Success Response (200):**

```json
{
    "message": "Profile picture uploaded successfully",
    "profilePicture": "https://res.cloudinary.com/your-cloud/image/upload/v123456/beatmarket/profiles/user_1_1234567890.jpg"
}
```

**Error Responses:**

-   **401 Unauthorized:** No valid JWT token

```json
{
    "error": "Unauthorized"
}
```

-   **400 Bad Request:** No file provided

```json
{
    "error": "No file provided"
}
```

-   **400 Bad Request:** Invalid file type

```json
{
    "error": "Invalid file type. Only images are allowed (jpeg, jpg, png, gif, webp)"
}
```

-   **400 Bad Request:** File too large

```json
{
    "error": "File too large. Maximum size is 5MB"
}
```

---

### 2. Delete Profile Picture

**DELETE** `/api/user/profile-picture`

Delete the current user's profile picture from both Cloudinary and the database.

**Headers:**

```
Authorization: Bearer {your-jwt-token}
```

**Example using cURL:**

```bash
curl -X DELETE http://localhost:8000/api/user/profile-picture \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Success Response (200):**

```json
{
    "message": "Profile picture deleted successfully"
}
```

**Error Responses:**

-   **401 Unauthorized:** No valid JWT token

```json
{
    "error": "Unauthorized"
}
```

-   **400 Bad Request:** No profile picture exists

```json
{
    "error": "No profile picture to delete"
}
```

---

### 3. Get User Profile

**GET** `/api/user/profile`

Retrieve the current user's profile including the profile picture URL.

**Headers:**

```
Authorization: Bearer {your-jwt-token}
```

**Example using cURL:**

```bash
curl -X GET http://localhost:8000/api/user/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Success Response (200):**

```json
{
    "id": 1,
    "email": "user@example.com",
    "username": "johndoe",
    "phone": "123456789",
    "profilePicture": "https://res.cloudinary.com/your-cloud/image/upload/v123456/beatmarket/profiles/user_1_1234567890.jpg",
    "role": "ROLE_USER",
    "isVerified": true,
    "createdAt": "2025-12-01 10:30:00",
    "wallet": {
        "balance": 100.5
    }
}
```

---

## Features

1. **Automatic Cleanup:** When uploading a new profile picture, the old one is automatically deleted from Cloudinary
2. **Validation:** File type and size validation before upload
3. **Unique Naming:** Each uploaded file gets a unique public ID based on user ID and timestamp
4. **Organized Storage:** All profile pictures are stored in `beatmarket/profiles` folder on Cloudinary
5. **Database Integration:** The Cloudinary URL is automatically saved to the database

## Technical Details

-   **Service Used:** `CloudinaryService`
-   **Database Field:** `User.profilePicture` (nullable string, max 255 characters)
-   **Cloudinary Folder:** `beatmarket/profiles`
-   **Public ID Format:** `user_{userId}_{timestamp}`

## Testing Flow

1. **Login to get JWT token:**

    ```bash
    curl -X POST http://localhost:8000/api/auth/login \
      -H "Content-Type: application/json" \
      -d '{"email":"user@example.com","password":"yourpassword"}'
    ```

2. **Upload profile picture:**

    ```bash
    curl -X POST http://localhost:8000/api/user/profile-picture \
      -H "Authorization: Bearer YOUR_JWT_TOKEN" \
      -F "file=@./profile.jpg"
    ```

3. **Verify profile picture in profile:**

    ```bash
    curl -X GET http://localhost:8000/api/user/profile \
      -H "Authorization: Bearer YOUR_JWT_TOKEN"
    ```

4. **Delete profile picture (optional):**
    ```bash
    curl -X DELETE http://localhost:8000/api/user/profile-picture \
      -H "Authorization: Bearer YOUR_JWT_TOKEN"
    ```

## Notes

-   The profile picture URL can also be updated manually using `PUT /api/user/settings` with `{"profilePicture": "url"}` but using the upload endpoint is recommended
-   Make sure your Cloudinary credentials are properly configured in your `.env` file
-   The old implementation allowed setting the URL directly via the settings endpoint - this still works for backward compatibility
