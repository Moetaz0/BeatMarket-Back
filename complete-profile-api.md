# Complete Profile Endpoint

## Overview

After registration, users can complete their profile by adding phone number, profile picture, and selecting their role.

## Endpoint

**PUT** `/api/user/complete-profile`

Complete user profile after registration by adding optional profile information.

### Headers

```
Authorization: Bearer {your-jwt-token}
Content-Type: application/json
```

### Request Body

All fields are optional - only include the fields you want to update:

```json
{
    "phone": "123456789",
    "profilePicture": "https://res.cloudinary.com/your-cloud/image/upload/...",
    "role": "ROLE_ARTIST"
}
```

### Request Body Parameters

| Field            | Type   | Required | Description                                                |
| ---------------- | ------ | -------- | ---------------------------------------------------------- |
| `phone`          | string | No       | User's phone number (max 15 characters)                    |
| `profilePicture` | string | No       | Cloudinary URL of the profile picture                      |
| `role`           | string | No       | User role: `ROLE_ARTIST`, `ROLE_BEATMAKER`, or `ROLE_USER` |

**Note:** The role can be provided with or without the `ROLE_` prefix (e.g., `"ARTIST"` or `"ROLE_ARTIST"`)

## Examples

### Example 1: Complete Profile with All Fields

```bash
curl -X PUT http://localhost:8000/api/user/complete-profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+1234567890",
    "profilePicture": "https://res.cloudinary.com/demo/image/upload/v1/profiles/user_1.jpg",
    "role": "ROLE_ARTIST"
  }'
```

**Response (200 OK):**

```json
{
    "message": "Profile completed successfully",
    "user": {
        "id": 1,
        "email": "user@example.com",
        "username": "johndoe",
        "phone": "+1234567890",
        "profilePicture": "https://res.cloudinary.com/demo/image/upload/v1/profiles/user_1.jpg",
        "role": "ROLE_ARTIST",
        "isVerified": true
    }
}
```

### Example 2: Add Only Phone Number

```bash
curl -X PUT http://localhost:8000/api/user/complete-profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+1234567890"
  }'
```

### Example 3: Add Only Role

```bash
curl -X PUT http://localhost:8000/api/user/complete-profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "role": "BEATMAKER"
  }'
```

### Example 4: Using JavaScript Fetch

```javascript
async function completeProfile(phone, profilePictureUrl, role, token) {
    const response = await fetch(
        "http://localhost:8000/api/user/complete-profile",
        {
            method: "PUT",
            headers: {
                Authorization: `Bearer ${token}`,
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                phone: phone,
                profilePicture: profilePictureUrl,
                role: role,
            }),
        }
    );

    return await response.json();
}

// Usage
const token = localStorage.getItem("jwt_token");
const result = await completeProfile(
    "+1234567890",
    "https://cloudinary-url/profile.jpg",
    "ROLE_ARTIST",
    token
);

console.log(result);
```

## Error Responses

### 401 Unauthorized

No valid JWT token provided.

```json
{
    "error": "Unauthorized"
}
```

### 400 Bad Request - Invalid Role

When an invalid role is provided.

```json
{
    "error": "Invalid role. Allowed roles: ROLE_ARTIST, ROLE_BEATMAKER, ROLE_USER"
}
```

## Typical User Flow

1. **Register**

    ```bash
    POST /api/auth/register
    ```

    Create account with email, username, and password.

2. **Verify Email**

    ```bash
    POST /api/auth/verify
    ```

    Verify email with code received.

3. **Login**

    ```bash
    POST /api/auth/login
    ```

    Get JWT token.

4. **Complete Profile** ⭐ (This endpoint)

    ```bash
    PUT /api/user/complete-profile
    ```

    Add phone, profile picture, and select role.

5. **Continue Using App**
   Profile is now complete!

## Combining with Profile Picture Upload

For the best user experience, you can upload the profile picture first, then use the returned URL:

```javascript
// Step 1: Upload profile picture
const formData = new FormData();
formData.append("file", fileInput.files[0]);

const uploadResponse = await fetch(
    "http://localhost:8000/api/user/profile-picture",
    {
        method: "POST",
        headers: {
            Authorization: `Bearer ${token}`,
        },
        body: formData,
    }
);

const uploadData = await uploadResponse.json();
const profilePictureUrl = uploadData.profilePicture;

// Step 2: Complete profile with the uploaded image URL
const completeResponse = await fetch(
    "http://localhost:8000/api/user/complete-profile",
    {
        method: "PUT",
        headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            phone: "+1234567890",
            profilePicture: profilePictureUrl,
            role: "ROLE_ARTIST",
        }),
    }
);

const result = await completeResponse.json();
console.log("Profile completed:", result);
```

## Notes

-   All fields are optional - you can update any combination of fields
-   The endpoint can be called multiple times to update different fields
-   Phone number is limited to 15 characters (as per database schema)
-   Profile picture should be a valid URL (preferably from Cloudinary after upload)
-   Role prefix `ROLE_` is automatically added if not present
-   Only specific roles are allowed: ARTIST, BEATMAKER, USER

## Difference from `/api/user/settings`

-   **`/api/user/complete-profile`**: Designed for initial profile setup after registration, allows role selection
-   **`/api/user/settings`**: General settings update, includes username changes and other settings

Both endpoints can update phone and profilePicture, but `complete-profile` is specifically designed for the onboarding flow and allows role selection.
