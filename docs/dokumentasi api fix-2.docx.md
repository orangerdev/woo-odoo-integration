**E-commerce Backend API Documentation**

**Overview**

API Backend untuk sistem e-commerce dengan fitur manajemen penjualan, customer, dan inventory management.

**Base URL:** https://api-pipe.bumilindo.co.id/v1  
**Authentication:** Bearer Token  
**Content-Type:** application/json

**Authentication**

Semua endpoint (kecuali login) memerlukan authentication header:

Authorization: Bearer {jwt-token}

**Endpoints**

1. **Login**  
   Digunakan untuk mendapatkan jwt token Authorization

   **Request**  

   **Type		\= POST**  
   **URL		\=** https://api-pipe.bumilindo.co.id/v1/login-ecommerce

   **Content-Type**	\= application/json

   **Body**

   {

     "Username"    : "coba@Bumilindo.com",

     "Password"      : "12345"

   }

	**Respose Success(200)**

{

    "data": {

        "access\_token": "15146546",

        "expired\_at": "2025-06-10 13:47:57.175",

        "username": "coba@Bumilindo.com"

    },

    "message": "Success",

    "success": **true**

}

**Response Error (400):**

{

    "error": "User not found"

}

**Response Error (401):**

{

    "error": "Invalid password"

}

	**Field Descriptions:**	

| Field | Type | Required | Description |
| :---- | :---- | :---- | :---- |
| Username | String | Yes | Email digunakan untuk login |
| Password | String | Yes | Password dari username  |

2. **GetPRoduct**  
   Untuk mendapatkan data product  
   **Request**  

   **Type		\= GET**  
   **URL		\=** https://api-pipe.bumilindo.co.id/v1/getproduct-ecommerce  
   **Content-Type**	\= application/json  
   **Authorization	\=** Bearer {token}

   

	**Respose Success(200)**

{

    "success": **true**,

    "error": **null**,

    "message": "",

    "process\_time": 0.2159985,

    "records": 1,

    "data": \[

        {

            "cabang": "BL001/09",

            "nama\_cabang": "BUMILINK \- SES JENGGOLO",

            "gudang\_utama": "BL001/09/0000001",

            "kode\_variant": "010335EDA",

            "nama\_variant": "SAMSUNG S928 12/256 BLACK SEIN \- S24 ULTRA",

            "ukuran": "12/256",

            "warna": "BLACK",

            "spesifikasi": "S24 ULTRA",

            "path\_gambar": "link gambar",

            "harga": "17999000.0000",

            "stok": "1",

            "child\_kategori": "S Series",

            "parent\_kategori": "HANDPHONE",

            "product\_type": "UMUM",

            "alias\_kode2\_product": "Samsung Galaxy S25 Ultra SEIN Resmi",

            "spesifikasi2\_product": "Spesifikasi Utama:\<br\>\<br\>Layar 6.9” Dynamic LTPO AMOLED 2X, QHD+, 120Hz, Gorilla Glass Victus 2\<br\>Prosesor Snapdragon 8 Elite for Galaxy (3nm)\<br\>Kamera belakang: 200 MP utama \+ 12 MP ultra-wide \+ 50 MP telephoto (5x) \+ 10 MP telephoto (3x)\<br\>Kamera depan: 12MP\<br\>Baterai 5.000 mAh, Fast Charging 45W \+ Wireless Charging\<br\>OS: Android 15, One UI 7, Galaxy AI\<br\>Tahan air & debu IP68\<br\>\<br\> Kelebihan:\<br\>\<br\>Performa super cepat untuk gaming & multitasking\<br\>Kamera jernih dengan zoom optik 3x\<br\>Layar cerah & halus dengan refresh rate adaptif\<br\>Desain compact, ringan, dan premium\<br\>\<br\> Isi Paket:\<br\>1x Samsung Galaxy S25\<br\>1x Kabel USB-C\<br\>1x SIM Ejector\<br\>Buku panduan & kartu garansi resmi"

        }

\]

}

**Response Error (401):**  
		\=\> token yang diinput salah atau expired

{

    "error": "invalid credential",

    "extras": **null**,

    "process\_time": 0.000704858,

    "success": **false**

}

3. **CheckOut**  
   Digunakan untuk simpan penjualan ke altius

   **Request:**

   **Type		\= POST**  
   **URL		\=** https://api-pipe.bumilindo.co.id/v1/checkout-ecommerce

   Content-Type: application/json

   Authorization: Bearer {token}

   **Request Body:**

   {

   "gudang": "BL001/09/0000001",

   	  "transaction\_id": "TXN-2025-101",

   	  "tanggal": "2025-05-10",

   	  "keterangan": "Penjualan reguler",

   	  "email\_customer": "customer@email.com",

   	  "nama\_customer": "Adi",

   	  "alamat": "Jl. Contoh No. 123",

   	  "telp": "081234567890",

   	  "tanggal\_lahir": "1990-01-01",

   	  "nik": "1234567890123456",

   	  "detail": \[

   		{

   		  "sku": "010335EDA",

   		  "flag": "NO",

   		  "harga": "17999000",

   		  "qty": "1",

   		  "disc\_rp": "0",

   		  "disc\_persen": "0"

   		},

   		{

   		  "sku": "010335EDB",

   		  "flag": "NO", 

   		  "harga": "17999000",

   		  "qty": "1",

   		  "disc\_rp": "0",

   		  "disc\_persen": "0"

   		}

   	  \]

   	  "detailpembayaran": \[

   		{

   		  "bentuk\_dana": "TRANSFER",

   		  "setor\_kebank": "BL001/1010102101001",

   		  "bank": "BCA",

   		  "nomor\_kartukredit": "1234",

   		  "nominal\_payment": "35998000",

   		},

   		{

   		  "bentuk\_dana": "KARTU KREDIT",

   		  "setor\_kebank": "BL001/1010102101001",

   		  "bank": "BCA",

   		  "nomor\_kartukredit": "1234",

   		  "nominal\_payment": "15998000",

   		}

   	  \]

   	}

   **Response Success (200):**

   {

       "success": **true**,

       "message": "Data berhasil disimpan dengan transaction ID: TXN-2025-101",

       "details": **null**

   }

   **Response Error (500):**

   {

       "success": **false**,

       "message": "Failed to save data: Terjadi kesalahan dalam proses penyimpanan",

       "details": \[

           {

               "pesan": "Gudang BL001/09/0000000 Tidak Ditemukan",

               "transaction\_id": "TXN-2025-001"

           },

           {

               "pesan": "Gudang BL001/09/0000000 Tidak Ditemukan",

               "transaction\_id": "TXN-2025-001"

           }

       \]

   }

Field Descriptions:

| Field | Type | Required | Description |
| :---- | :---- | :---- | :---- |
| gudang | string | Yes | Kode gudang (max 100 chars) |
| transaction\_id | string | Yes | ID transaksi unik (max 100 chars) |
| tanggal | string | Yes | Tanggal transaksi (YYYY-MM-DD) |
| keterangan | string | No | Keterangan transaksi (max 500 chars) |
| email\_customer | string | Yes | Email customer (max 100 chars) |
| nama\_customer | string | Yes | Nama customer (max 100 chars) |
| alamat | string | Yes | Alamat customer (max 200 chars) |
| telp | string | Yes | Nomor telepon (max 50 chars) |
| tanggal\_lahir | string | No | Tanggal lahir (YYYY-MM-DD) |
| nik | string | Yes | NIK customer (max 16 chars) |
| bentuk\_dana | string | Yes | TRANSFER, KARTU KREDIT |
| bank | string | Yes | Nama bank (max 100 chars) |
| nomor\_kartu\_kredit | string | Yes | Nomor kartu (max 100 chars) |
| nominal\_payment | string | Yes | Total pembayaran |
| BankKartuKredit | string | Yes | Bank Kartu Kredit (max 50 chars) |
| detail | array | Yes | Array detail item |

**Detail Item Fields:**

| Field | Type | Required | Description |
| :---- | :---- | :---- | :---- |
| sku | string | Yes | SKU (productvariant) produk (max 100 chars) |
| flag | string | Yes | Status free item (YES/NO) |
| harga | string | Yes | Harga satuan |
| qty | string | Yes | Jumlah item |
| disc\_rp | string | No | Diskon dalam rupiah |
| disc\_persen | string | No | Diskon dalam persen |

