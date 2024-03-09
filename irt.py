import numpy as np

# Skor θ hipotetikal untuk 4 siswa
theta_scores = np.array([-0.5, 0, 0.5, 1.0])

# Fungsi untuk menormalisasi skor θ ke dalam rentang -3 hingga +3
def normalize_theta(theta_scores, target_min=-3, target_max=3):
    # Menghitung min dan max dari skor θ saat ini
    min_theta = np.min(theta_scores)
    max_theta = np.max(theta_scores)
    
    # Normalisasi skor θ ke rentang 0 hingga 1
    normalized_theta = (theta_scores - min_theta) / (max_theta - min_theta)
    
    # Skala ke rentang target dan kembalikan
    scaled_theta = normalized_theta * (target_max - target_min) + target_min
    return scaled_theta

# Normalisasi skor θ
normalized_theta_scores = normalize_theta(theta_scores)

print("Skor θ awal:", theta_scores)
print("Skor θ dinormalisasi:", normalized_theta_scores)

# Ilustrasi distribusi skor dengan rentang target
for i, theta in enumerate(normalized_theta_scores):
    print(f"Siswa {i+1}: Skor θ = {theta:.2f}")
