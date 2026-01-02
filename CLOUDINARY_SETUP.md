# Cloudinary Setup Guide

## Profile Picture Upload Feature

Your application now supports uploading profile pictures to Cloudinary and storing the URL in the database.

## Configuration

### 1. Get Cloudinary Credentials

1. Go to [Cloudinary](https://cloudinary.com/) and sign up/login
2. From your dashboard, copy your **CLOUDINARY_URL**
    - It looks like: `cloudinary://API_KEY:API_SECRET@CLOUD_NAME`

### 2. Configure Environment Variable

Add to your `.env` file:

```env
CLOUDINARY_URL=cloudinary://123456789012345:abcdefghijklmnopqrstuvwxyz@your-cloud-name
```

Replace with your actual Cloudinary URL from step 1.

### 3. Verify Configuration

The CloudinaryService is already configured in `config/services.yaml`:

```yaml
App\Service\CloudinaryService:
    arguments:
        $cloudinaryUrl: "%env(CLOUDINARY_URL)%"
```

## Database Schema

The `profilePicture` field is already in your User entity:

```php
#[ORM\Column(length: 255, nullable: true)]
private ?string $profilePicture = null;
```

## Available Endpoints

### Upload Profile Picture

-   **Endpoint:** `POST /api/user/profile-picture`
-   **Authentication:** Required (JWT)
-   **Content-Type:** `multipart/form-data`
-   **Field:** `file` (image file)
-   **Supported formats:** JPEG, JPG, PNG, GIF, WEBP
-   **Max size:** 5MB

### Delete Profile Picture

-   **Endpoint:** `DELETE /api/user/profile-picture`
-   **Authentication:** Required (JWT)

### Get Profile (includes profile picture URL)

-   **Endpoint:** `GET /api/user/profile`
-   **Authentication:** Required (JWT)

## How It Works

1. **Upload:**

    - User sends image file via POST request
    - File is validated (type and size)
    - Old profile picture is deleted from Cloudinary (if exists)
    - New image is uploaded to Cloudinary in `beatmarket/profiles` folder
    - Cloudinary returns a secure URL
    - URL is saved to database in `users.profile_picture` column

2. **Delete:**

    - User sends DELETE request
    - Image is deleted from Cloudinary
    - Database field is set to NULL

3. **Retrieve:**
    - Profile picture URL is returned with user profile data
    - Frontend can display the image using the URL

## Testing

See [test-profile-picture-upload.md](test-profile-picture-upload.md) for detailed API testing instructions.

### Quick Test with cURL

```bash
# 1. Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"your@email.com","password":"yourpassword"}'

# 2. Upload profile picture (replace YOUR_JWT_TOKEN)
curl -X POST http://localhost:8000/api/user/profile-picture \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@path/to/your/image.jpg"

# 3. Check profile
curl -X GET http://localhost:8000/api/user/profile \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Frontend Integration Example

### Using Fetch API (JavaScript)

```javascript
// Upload profile picture
async function uploadProfilePicture(file, token) {
    const formData = new FormData();
    formData.append("file", file);

    const response = await fetch(
        "http://localhost:8000/api/user/profile-picture",
        {
            method: "POST",
            headers: {
                Authorization: `Bearer ${token}`,
            },
            body: formData,
        }
    );

    const data = await response.json();
    return data.profilePicture; // Returns Cloudinary URL
}

// Usage in React/Vue/etc
const handleFileChange = async (event) => {
    const file = event.target.files[0];
    const token = localStorage.getItem("jwt_token");

    try {
        const imageUrl = await uploadProfilePicture(file, token);
        console.log("Profile picture uploaded:", imageUrl);
        // Update UI with new image URL
    } catch (error) {
        console.error("Upload failed:", error);
    }
};
```

### HTML Form

```html
<form id="uploadForm">
    <input type="file" id="profilePicture" accept="image/*" required />
    <button type="submit">Upload Profile Picture</button>
</form>

<script>
    document
        .getElementById("uploadForm")
        .addEventListener("submit", async (e) => {
            e.preventDefault();

            const file = document.getElementById("profilePicture").files[0];
            const formData = new FormData();
            formData.append("file", file);

            const token = localStorage.getItem("jwt_token");

            const response = await fetch(
                "http://localhost:8000/api/user/profile-picture",
                {
                    method: "POST",
                    headers: {
                        Authorization: `Bearer ${token}`,
                    },
                    body: formData,
                }
            );

            const result = await response.json();
            console.log(result);
        });
</script>
```

## Security Features

✅ **File Type Validation:** Only images are allowed
✅ **File Size Limit:** Maximum 5MB
✅ **Authentication Required:** JWT token required
✅ **Automatic Cleanup:** Old pictures are deleted when uploading new ones
✅ **Unique Naming:** Files are named with user ID and timestamp to avoid conflicts

## Notes

-   Profile pictures are stored in the `beatmarket/profiles` folder on Cloudinary
-   The URL format is: `https://res.cloudinary.com/YOUR_CLOUD_NAME/image/upload/v{version}/beatmarket/profiles/user_{id}_{timestamp}.{ext}`
-   When a user uploads a new profile picture, the old one is automatically deleted
-   The database only stores the URL, not the actual file
-   URLs are accessible without authentication (Cloudinary serves them publicly)
