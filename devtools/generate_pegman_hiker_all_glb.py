"""
Generador del modelo Pegman Hiker 3D con flecha adelantada y 14 animaciones.
Formato: glTF 2.0 binario (.glb)
Requisitos: Python 3.8+ y numpy (pip install numpy)
 =================================================================================================
 PEGMAN HIKER 3D (glTF 2.0 / GLB) - CATÁLOGO DE ANIMACIONES INTEGRADAS
 =================================================================================================

 Formato: Multi-Track glTF Animation Clips
 Materiales: PBR 100% Mate (Roughness 0.85 - 0.95, Metallic 0.0)
 Orientación del Rig: Eje +Y hacia arriba, avance hacia +Z horizontal

 -------------------------------------------------------------------------------------------------
 ID ANIMACIÓN                  DURACIÓN    FLECHA              DESCRIPCIÓN CINEMÁTICA / CONTENIDO
 -------------------------------------------------------------------------------------------------

 1. "Caminando"                1.2s Loop   Inactiva (scale=0)  Ciclo de marcha continuo. Zancada
                                                               alternativa con flexión de rodillas,
                                                               oscilación cadencial de pelvis, codos
                                                               flexionados hacia adelante, brazo
                                                               izquierdo traccionando con el bastón
                                                               y derecho en balanceo pendular libre.

 2. "Descansando"              2.5s Loop   Inactiva (scale=0)  Postura estática en contrapposto.
                                                               Brazo izquierdo y torso descargando peso
                                                               sobre el bastón vertical plantado en el
                                                               suelo (Y=0), brazo derecho en jarras con
                                                               codo proyectado al costado (81°) y mano
                                                               en la cintura. Micro-respiración en torso.

 3. "Apuntando"                1.0s Loop   Inactiva (scale=0)  Postura estática de señalamiento manual.
                                                               Brazo derecho alzado hacia el frente con
                                                               índice extendido, realizando dos pulsos
                                                               rítmicos por segundo de flexión/extensión.
                                                               Brazo izquierdo afirma el bastón en suelo.

 4. "Desorientado"             3.6s Loop   Inactiva (scale=0)  Meme de John Travolta en Pulp Fiction
                                                               (Vincent Vega). Barrido perplejo de torso
                                                               y giro amplio de cabeza con inclinación
                                                               curiosa (tilt), brazos abiertos a los lados
                                                               con palmas hacia arriba en ademán de
                                                               extrañeza y bastón apartado hacia el flanco.

 5. "Caminando_Apuntando"      1.2s Loop   Inactiva (scale=0)  Marcha activa y señalamiento simultáneo.
                                                               Tren inferior y brazo izquierdo mantienen
                                                               el paso y apoyo del bastón mientras el
                                                               brazo derecho permanece levantado al frente
                                                               marcando rumbo con el dedo índice.

 6. "Caminando_Apuntando_Flecha" 1.2s Loop Activa (pulso Z)    Navegación y guiado activo sobre el mapa.
                                                               Misma cinemática de marcha y brazo alzado
                                                               que el modo anterior, pero con la flecha
                                                               3D adelantada visible y pulsando en
                                                               longitud (scale_z entre 0.85x y 1.15x).

 7. "Apuntando_Flecha"         1.0s Loop   Activa (pulso Z)    Marcador estático de dirección / POI.
                                                               Muñeco estático señalando al frente con
                                                               el dedo índice, acompañado por la flecha
                                                               horizontal adelantada activa y pulsando
                                                               rítmicamente sobre el vector de avance.

 8. "Caminando_Desorientado"  3.6s Loop   Inactiva (scale=0)  Marcha continua con los gestos de
                                                               Desorientado en torso, cabeza y brazos.

 9. "buscando"                7.9s Loop   Inactiva (scale=0)  Caminando, Descansando y Desorientado
                                                               concatenados con transiciones cortas.

10. "buscando_brújula"        2.6s Loop   Activa (pulso Z)    Caminando_Apuntando_Flecha y
                                                               Apuntando_Flecha concatenados.

11. "sentanse"                2.4s        Inactiva           Se agacha, apoya una mano y se sienta
                                                               con una pierna flexionada y otra extendida.

12. "Sentado saludando"      10.0s Loop   Inactiva           Apoya una mano detrás del cuerpo, mira
                                                               al cielo y saluda con la otra.

13. "Levantarse"             2.4s        Inactiva           Se inclina hacia delante y recupera
                                                               la posición erguida.

14. "sentarysaludar"        15.4s Loop   Inactiva           sentanse + Sentado saludando (10 s)
                                                               + Levantarse, con transiciones.

"""
import json
import struct
from pathlib import Path
import numpy as np

# ==============================================================================
# 1. UTILIDADES CINEMÁTICAS Y CUATERNIONES
# ==============================================================================

def axis_angle_to_quat(axis, angle_rad):
    """Genera un cuaternión [x, y, z, w] para rotaciones en torno a un eje."""
    axis = np.array(axis, dtype=np.float32)
    axis = axis / np.linalg.norm(axis)
    s = np.sin(angle_rad / 2.0)
    c = np.cos(angle_rad / 2.0)
    return np.array([axis[0] * s, axis[1] * s, axis[2] * s, c], dtype=np.float32)

def quat_mul(q1, q2):
    """Multiplicación hamiltoniana de dos cuaterniones q1 * q2."""
    x1, y1, z1, w1 = q1
    x2, y2, z2, w2 = q2
    return np.array([
        w1*x2 + x1*w2 + y1*z2 - z1*y2,
        w1*y2 - x1*z2 + y1*w2 + z1*x2,
        w1*z2 + x1*y2 - y1*x2 + z1*w2,
        w1*w2 - x1*x2 - y1*y2 - z1*z2
    ], dtype=np.float32)

# ==============================================================================
# 2. GENERADORES GEOMÉTRICOS DE MALLAS 3D
# ==============================================================================

def make_box(min_pt, max_pt):
    """Genera un ortoedro alineado con los ejes cartesianos."""
    x0, y0, z0 = min_pt
    x1, y1, z1 = max_pt
    faces = [
        ([[x0,y0,z1],[x1,y0,z1],[x1,y1,z1],[x0,y1,z1]], [0.0, 0.0, 1.0]),
        ([[x1,y0,z0],[x0,y0,z0],[x0,y1,z0],[x1,y1,z0]], [0.0, 0.0, -1.0]),
        ([[x0,y1,z1],[x1,y1,z1],[x1,y1,z0],[x0,y1,z0]], [0.0, 1.0, 0.0]),
        ([[x0,y0,z0],[x1,y0,z0],[x1,y0,z1],[x0,y0,z1]], [0.0, -1.0, 0.0]),
        ([[x1,y0,z1],[x1,y0,z0],[x1,y1,z0],[x1,y1,z1]], [1.0, 0.0, 0.0]),
        ([[x0,y0,z0],[x0,y0,z1],[x0,y1,z1],[x0,y1,z0]], [-1.0, 0.0, 0.0]),
    ]
    verts, norms, idxs = [], [], []
    for quad, norm in faces:
        base = len(verts)
        for p in quad:
            verts.append(p)
            norms.append(norm)
        idxs.extend([base, base + 1, base + 2, base, base + 2, base + 3])
    return np.array(verts, dtype=np.float32), np.array(norms, dtype=np.float32), np.array(idxs, dtype=np.uint16)

def make_sphere(radius, center=(0.0, 0.0, 0.0), lats=14, lons=20):
    """Genera una esfera uv con normales continuas."""
    cx, cy, cz = center
    verts, norms, idxs = [], [], []
    for i in range(lats + 1):
        phi = np.pi * i / lats
        s_phi, c_phi = np.sin(phi), np.cos(phi)
        for j in range(lons + 1):
            theta = 2 * np.pi * j / lons
            nx, ny, nz = s_phi * np.sin(theta), c_phi, s_phi * np.cos(theta)
            verts.append([cx + radius * nx, cy + radius * ny, cz + radius * nz])
            norms.append([nx, ny, nz])
    for i in range(lats):
        for j in range(lons):
            f = i * (lons + 1) + j
            s = f + lons + 1
            idxs.extend([f, s, f + 1, s, s + 1, f + 1])
    return np.array(verts, dtype=np.float32), np.array(norms, dtype=np.float32), np.array(idxs, dtype=np.uint16)

def make_cylinder_x(radius, length, center=(0.0, 0.0, 0.0), segments=16):
    """Genera un cilindro horizontal en el eje X para la esterilla."""
    cx, cy, cz = center
    theta = np.linspace(0, 2 * np.pi, segments, endpoint=False)
    hl = length / 2.0
    verts, norms, idxs = [], [], []
    c1 = len(verts); verts.append([cx + hl, cy, cz]); norms.append([1.0, 0.0, 0.0])
    for i in range(segments):
        verts.append([cx + hl, cy + radius * np.cos(theta[i]), cz + radius * np.sin(theta[i])]); norms.append([1.0, 0.0, 0.0])
        idxs.extend([c1, c1 + 1 + i, c1 + 1 + ((i + 1) % segments)])
    c2 = len(verts); verts.append([cx - hl, cy, cz]); norms.append([-1.0, 0.0, 0.0])
    for i in range(segments):
        verts.append([cx - hl, cy + radius * np.cos(theta[i]), cz + radius * np.sin(theta[i])]); norms.append([-1.0, 0.0, 0.0])
        idxs.extend([c2, c2 + 1 + ((i + 1) % segments), c2 + 1 + i])
    for i in range(segments):
        nxt = (i + 1) % segments
        b = len(verts)
        p0 = [cx - hl, cy + radius * np.cos(theta[i]), cz + radius * np.sin(theta[i])]
        p1 = [cx + hl, cy + radius * np.cos(theta[i]), cz + radius * np.sin(theta[i])]
        p2 = [cx + hl, cy + radius * np.cos(theta[nxt]), cz + radius * np.sin(theta[nxt])]
        p3 = [cx - hl, cy + radius * np.cos(theta[nxt]), cz + radius * np.sin(theta[nxt])]
        n = [0.0, np.cos(theta[i]), np.sin(theta[i])]
        verts.extend([p0, p1, p2, p3])
        norms.extend([n, n, [0.0, np.cos(theta[nxt]), np.sin(theta[nxt])], [0.0, np.cos(theta[nxt]), np.sin(theta[nxt])]])
        idxs.extend([b, b + 1, b + 2, b, b + 2, b + 3])
    return np.array(verts, dtype=np.float32), np.array(norms, dtype=np.float32), np.array(idxs, dtype=np.uint16)

def make_cylinder_between_points(p0, p1, radius, segments=12):
    """Genera un cilindro orientado entre dos puntos cartesianos 3D."""
    p0, p1 = np.array(p0, dtype=np.float32), np.array(p1, dtype=np.float32)
    v_dir = p1 - p0
    length = np.linalg.norm(v_dir)
    dir_u = v_dir / length
    perp = np.cross(dir_u, [1.0, 0.0, 0.0] if abs(dir_u[0]) < 0.9 else [0.0, 1.0, 0.0])
    perp = perp / np.linalg.norm(perp)
    perp2 = np.cross(dir_u, perp)
    theta = np.linspace(0, 2 * np.pi, segments, endpoint=False)
    verts, norms, idxs = [], [], []
    c0 = len(verts); verts.append(p0.tolist()); norms.append((-dir_u).tolist())
    for i in range(segments):
        pt = p0 + radius * (np.cos(theta[i]) * perp + np.sin(theta[i]) * perp2)
        verts.append(pt.tolist()); norms.append((-dir_u).tolist())
        idxs.extend([c0, c0 + 1 + ((i + 1) % segments), c0 + 1 + i])
    c1 = len(verts); verts.append(p1.tolist()); norms.append(dir_u.tolist())
    for i in range(segments):
        pt = p1 + radius * (np.cos(theta[i]) * perp + np.sin(theta[i]) * perp2)
        verts.append(pt.tolist()); norms.append(dir_u.tolist())
        idxs.extend([c1, c1 + 1 + i, c1 + 1 + ((i + 1) % segments)])
    for i in range(segments):
        nxt = (i + 1) % segments
        b = len(verts)
        v0 = p0 + radius * (np.cos(theta[i]) * perp + np.sin(theta[i]) * perp2)
        v1 = p1 + radius * (np.cos(theta[i]) * perp + np.sin(theta[i]) * perp2)
        v2 = p1 + radius * (np.cos(theta[nxt]) * perp + np.sin(theta[nxt]) * perp2)
        v3 = p0 + radius * (np.cos(theta[nxt]) * perp + np.sin(theta[nxt]) * perp2)
        n0 = (np.cos(theta[i]) * perp + np.sin(theta[i]) * perp2).tolist()
        n1 = (np.cos(theta[nxt]) * perp + np.sin(theta[nxt]) * perp2).tolist()
        verts.extend([v0.tolist(), v1.tolist(), v2.tolist(), v3.tolist()])
        norms.extend([n0, n0, n1, n1])
        idxs.extend([b, b + 1, b + 2, b, b + 2, b + 3])
    return np.array(verts, dtype=np.float32), np.array(norms, dtype=np.float32), np.array(idxs, dtype=np.uint16)

def make_cone_frustum_between_points(p0, p1, r0, r1, segments=16):
    """Genera un tronco de cono o cono puntual entre dos posiciones."""
    p0, p1 = np.array(p0, dtype=np.float32), np.array(p1, dtype=np.float32)
    v_dir = p1 - p0
    dir_u = v_dir / np.linalg.norm(v_dir)
    perp = np.cross(dir_u, [1.0, 0.0, 0.0] if abs(dir_u[0]) < 0.9 else [0.0, 1.0, 0.0])
    perp = perp / np.linalg.norm(perp)
    perp2 = np.cross(dir_u, perp)
    theta = np.linspace(0, 2 * np.pi, segments, endpoint=False)
    c_th, s_th = np.cos(theta), np.sin(theta)
    verts, norms, idxs = [], [], []
    if r0 > 1e-4:
        c0 = len(verts); verts.append(p0.tolist()); norms.append((-dir_u).tolist())
        for i in range(segments):
            pt = p0 + r0 * (c_th[i] * perp + s_th[i] * perp2)
            verts.append(pt.tolist()); norms.append((-dir_u).tolist())
            idxs.extend([c0, c0 + 1 + ((i + 1) % segments), c0 + 1 + i])
    c1 = len(verts); verts.append(p1.tolist()); norms.append(dir_u.tolist())
    for i in range(segments):
        pt = p1 + r1 * (c_th[i] * perp + s_th[i] * perp2)
        verts.append(pt.tolist()); norms.append(dir_u.tolist())
        idxs.extend([c1, c1 + 1 + i, c1 + 1 + ((i + 1) % segments)])
    for i in range(segments):
        nxt = (i + 1) % segments
        b = len(verts)
        v0 = p0 + r0 * (c_th[i] * perp + s_th[i] * perp2)
        v1 = p1 + r1 * (c_th[i] * perp + s_th[i] * perp2)
        v2 = p1 + r1 * (c_th[nxt] * perp + s_th[nxt] * perp2)
        v3 = p0 + r0 * (c_th[nxt] * perp + s_th[nxt] * perp2)
        n0 = (c_th[i] * perp + s_th[i] * perp2).tolist()
        n1 = (c_th[nxt] * perp + s_th[nxt] * perp2).tolist()
        verts.extend([v0.tolist(), v1.tolist(), v2.tolist(), v3.tolist()])
        norms.extend([n0, n0, n1, n1])
        idxs.extend([b, b + 1, b + 2, b, b + 2, b + 3])
    return np.array(verts, dtype=np.float32), np.array(norms, dtype=np.float32), np.array(idxs, dtype=np.uint16)

def make_chest_directional_arrow(length=0.65, r_shaft=0.024, r_head=0.068, head_len=0.22):
    """
    Construye la flecha direccional horizontal apuntando estrictamente al frente (+Z).
    Su origen local [0, 0, 0] queda delante del pecho por la traslación del nodo.
    """
    p_start = [0.0, 0.0, 0.0]
    p_neck = [0.0, 0.0, length - head_len]
    p_end = [0.0, 0.0, length]
    v_shaft, n_shaft, i_shaft = make_cylinder_between_points(p_start, p_neck, r_shaft, segments=16)
    v_head, n_head, i_head = make_cone_frustum_between_points(p_neck, p_end, r_head, 0.001, segments=18)
    return combine_prims([(v_shaft, n_shaft, i_shaft), (v_head, n_head, i_head)])

def combine_prims(parts):
    """Fusiona geometrías que comparten el mismo índice de material."""
    all_v, all_n, all_i = [], [], []
    offset = 0
    for v, n, i in parts:
        all_v.append(v)
        all_n.append(n)
        all_i.append(i + offset)
        offset += len(v)
    return np.vstack(all_v), np.vstack(all_n), np.concatenate(all_i)

# ==============================================================================
# 3. ENSAMBLADOR DE ESTRUCTURA GLTF 2.0 / GLB
# ==============================================================================

class FullGLBGenerator:
    def __init__(self):
        self.bin_bytes = bytearray()
        self.buffer_views, self.accessors, self.materials = [], [], []
        self.meshes, self.nodes, self.animations = [], [], []

    def add_buffer_view(self, data_bytes, target=None):
        offset = len(self.bin_bytes)
        self.bin_bytes.extend(data_bytes)
        pad = (4 - len(self.bin_bytes) % 4) % 4
        self.bin_bytes.extend(b'\x00' * pad)
        bv = {"buffer": 0, "byteOffset": offset, "byteLength": len(data_bytes)}
        if target:
            bv["target"] = target
        idx = len(self.buffer_views)
        self.buffer_views.append(bv)
        return idx

    def add_accessor(self, bv_idx, component_type, count, type_str, min_val, max_val):
        acc = {
            "bufferView": bv_idx,
            "byteOffset": 0,
            "componentType": component_type,
            "count": count,
            "type": type_str,
            "min": min_val,
            "max": max_val
        }
        idx = len(self.accessors)
        self.accessors.append(acc)
        return idx

    def add_vec3_floats(self, arr):
        bv = self.add_buffer_view(arr.astype(np.float32).tobytes(), target=34962)
        return self.add_accessor(bv, 5126, len(arr), "VEC3", arr.min(axis=0).tolist(), arr.max(axis=0).tolist())

    def add_indices(self, arr):
        bv = self.add_buffer_view(arr.astype(np.uint16).tobytes(), target=34963)
        return self.add_accessor(bv, 5123, len(arr), "SCALAR", [int(arr.min())], [int(arr.max())])

    def add_time_accessor(self, times):
        bv = self.add_buffer_view(times.astype(np.float32).tobytes())
        return self.add_accessor(bv, 5126, len(times), "SCALAR", [float(times.min())], [float(times.max())])

    def add_vec3_keyframes(self, arr):
        bv = self.add_buffer_view(arr.astype(np.float32).tobytes())
        return self.add_accessor(bv, 5126, len(arr), "VEC3", arr.min(axis=0).tolist(), arr.max(axis=0).tolist())

    def add_quat_keyframes(self, arr):
        bv = self.add_buffer_view(arr.astype(np.float32).tobytes())
        return self.add_accessor(bv, 5126, len(arr), "VEC4", arr.min(axis=0).tolist(), arr.max(axis=0).tolist())

    def build_glb(self, filename):
        gltf = {
            "asset": {
                "version": "2.0",
                "generator": "Pegman Hiker 14-Animation Generator (Matte PBR)"
            },
            "scenes": [{"nodes": [0]}],
            "nodes": self.nodes,
            "meshes": self.meshes,
            "materials": self.materials,
            "animations": self.animations,
            "buffers": [{"byteLength": len(self.bin_bytes)}],
            "bufferViews": self.buffer_views,
            "accessors": self.accessors
        }
        json_b = json.dumps(gltf, separators=(',', ':')).encode('utf-8')
        pad = (4 - len(json_b) % 4) % 4
        json_b += b' ' * pad
        total_len = 12 + 8 + len(json_b) + 8 + len(self.bin_bytes)
        header = struct.pack('<4sII', b'glTF', 2, total_len)
        chunk0 = struct.pack('<II', len(json_b), 0x4E4F534A)
        chunk1 = struct.pack('<II', len(self.bin_bytes), 0x004E4942)
        with open(filename, 'wb') as f:
            f.write(header + chunk0 + json_b + chunk1 + bytes(self.bin_bytes))
        print(f"Modelo exportado exitosamente: '{filename}' ({total_len} bytes)")
        return total_len

# ==============================================================================
# 4. COMPILACIÓN DE MALLAS Y PISTAS DE ANIMACIÓN
# ==============================================================================

def generate_pegman_hiker_fixed_arrow(output_filename=None):
    if output_filename is None:
        output_filename = Path(__file__).resolve().parent.parent / "pix" / "pegman_hiker_all_animations_matte.glb"
    b = FullGLBGenerator()

    # --- MATERIALES 100% MATE (Sin reflejos plásticos: Roughness 0.85-0.95, Metallic 0.0) ---
    b.materials = [
        # 0: Pegman Amarillo Mate
        {"name": "Mat_Pegman_Yellow_Matte", "pbrMetallicRoughness": {"baseColorFactor": [0.965, 0.706, 0.118, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.92}, "doubleSided": True},
        # 1: Cuello Ámbar Mate
        {"name": "Mat_Amber_Collar_Matte", "pbrMetallicRoughness": {"baseColorFactor": [0.722, 0.388, 0.078, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.92}, "doubleSided": True},
        # 2: Lona Mochila Ocre Mate
        {"name": "Mat_Backpack_Tan_Matte", "pbrMetallicRoughness": {"baseColorFactor": [0.620, 0.482, 0.337, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.95}, "doubleSided": True},
        # 3: Esterilla Verde Mate
        {"name": "Mat_SleepingMat_Green_Matte", "pbrMetallicRoughness": {"baseColorFactor": [0.263, 0.651, 0.220, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.92}, "doubleSided": True},
        # 4: Correas Oscuras Mate
        {"name": "Mat_Straps_Dark_Matte", "pbrMetallicRoughness": {"baseColorFactor": [0.220, 0.176, 0.137, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.95}, "doubleSided": True},
        # 5: Vástago Bastón Madera Mate
        {"name": "Mat_Stick_Wood_Matte", "pbrMetallicRoughness": {"baseColorFactor": [0.369, 0.227, 0.118, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.92}, "doubleSided": True},
        # 6: Pomo y Regatón Bastón Mate
        {"name": "Mat_Stick_Tip_Matte", "pbrMetallicRoughness": {"baseColorFactor": [0.550, 0.550, 0.550, 1.0], "metallicFactor": 0.05, "roughnessFactor": 0.88}, "doubleSided": True},
        # 7: Flecha direccional adelantada (Matte Amber/Orange de alta visibilidad)
        {"name": "Mat_DirectionalArrow_Matte", "pbrMetallicRoughness": {"baseColorFactor": [1.0, 0.78, 0.10, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.85}, "doubleSided": True}
    ]

    def prim(v, n, i, mat):
        return {"attributes": {"POSITION": b.add_vec3_floats(v), "NORMAL": b.add_vec3_floats(n)}, "indices": b.add_indices(i), "material": mat}

    # Mesh 0: Torso + Mochila + Esterilla + Correas
    vy, ny, iy = combine_prims([
        make_box([-0.17, 0.0, -0.09], [0.17, 0.48, -0.07]),
        make_box([-0.17, 0.0, -0.07], [-0.14, 0.48, 0.09]),
        make_box([0.14, 0.0, -0.07], [0.17, 0.48, 0.09]),
        make_box([-0.14, 0.0, -0.07], [0.14, 0.02, 0.09]),
        make_box([-0.14, 0.46, -0.07], [0.14, 0.48, 0.02]),
        make_box([-0.14, 0.02, 0.07], [0.14, 0.18, 0.09]),
        make_box([-0.14, 0.18, 0.07], [-0.02, 0.48, 0.09]),
        make_box([0.02, 0.18, 0.07], [0.14, 0.48, 0.09])
    ])
    va, na, ia = make_box([-0.03, 0.18, 0.02], [0.03, 0.48, 0.07])
    vbp, nbp, ibp = combine_prims([
        make_box([-0.18, 0.04, -0.32], [0.18, 0.58, -0.09]),
        make_box([-0.14, 0.10, -0.36], [0.14, 0.42, -0.32]),
        make_box([-0.18, 0.54, -0.34], [0.18, 0.60, -0.10])
    ])
    vm, nm, im = make_cylinder_x(0.075, 0.44, center=(0.0, 0.66, -0.21), segments=18)
    vs, ns, is_ = combine_prims([
        make_cylinder_x(0.078, 0.025, center=(-0.12, 0.66, -0.21), segments=16),
        make_cylinder_x(0.078, 0.025, center=(0.12, 0.66, -0.21), segments=16),
        make_box([-0.16, 0.16, 0.088], [-0.11, 0.48, 0.098]),
        make_box([0.11, 0.16, 0.088], [0.16, 0.48, 0.098]),
        make_box([-0.16, 0.478, -0.10], [-0.11, 0.49, 0.09]),
        make_box([0.11, 0.478, -0.10], [0.16, 0.49, 0.09]),
        make_box([-0.175, 0.02, 0.085], [0.175, 0.07, 0.095])
    ])
    b.meshes.append({"name": "Mesh_Torso_Backpack", "primitives": [
        prim(vy, ny, iy, 0), prim(va, na, ia, 1), prim(vbp, nbp, ibp, 2), prim(vm, nm, im, 3), prim(vs, ns, is_, 4)
    ]})

    # Mesh 1: Cabeza
    v_hd, n_hd, i_hd = make_sphere(0.20, center=(0.0, 0.0, 0.0), lats=16, lons=24)
    b.meshes.append({"name": "Mesh_Head", "primitives": [prim(v_hd, n_hd, i_hd, 0)]})

    # Mesh 2: Brazo Superior Izquierdo
    v_lua, n_lua, i_lua = combine_prims([make_box([-0.045, -0.26, -0.045], [0.045, 0.0, 0.045]), make_sphere(0.045, (0,0,0), 8, 12)])
    b.meshes.append({"name": "Mesh_Left_UpperArm", "primitives": [prim(v_lua, n_lua, i_lua, 0)]})

    # Mesh 3: Antebrazo izquierdo. El bastón se mueve al nodo 12 para poder
    # ocultarlo al apoyar esta mano en el suelo sin perderlo en los demás clips.
    v_lfa, n_lfa, i_lfa = combine_prims([make_box([-0.04, -0.26, -0.04], [0.04, 0.0, 0.04]), make_sphere(0.045, (0, -0.26, 0.0), 8, 12)])
    p_knob = [0.0, -0.18, 0.04]
    p_tip = [0.0, -1.00, -0.25]
    v_sw, n_sw, i_sw = make_cylinder_between_points(p_knob, p_tip, 0.016, 12)
    v_sm, n_sm, i_sm = combine_prims([
        make_cylinder_between_points(p_tip, [0.0, -1.04, -0.265], 0.012, 10),
        make_cylinder_between_points(p_knob, [0.0, -0.14, 0.05], 0.022, 10)
    ])
    b.meshes.append({"name": "Mesh_Left_Forearm", "primitives": [prim(v_lfa, n_lfa, i_lfa, 0)]})

    # Mesh 4: Brazo Superior Derecho
    v_rua, n_rua, i_rua = combine_prims([make_box([-0.045, -0.26, -0.045], [0.045, 0.0, 0.045]), make_sphere(0.045, (0,0,0), 8, 12)])
    b.meshes.append({"name": "Mesh_Right_UpperArm", "primitives": [prim(v_rua, n_rua, i_rua, 0)]})

    # Mesh 5: Antebrazo Derecho + Mano con Dedo Índice
    v_rfa, n_rfa, i_rfa = combine_prims([
        make_box([-0.04, -0.26, -0.04], [0.04, 0.0, 0.04]),
        make_sphere(0.045, (0, -0.26, 0.0), 8, 12),
        make_cylinder_between_points([0.0, -0.26, 0.0], [0.0, -0.38, 0.0], 0.014, 10)
    ])
    b.meshes.append({"name": "Mesh_Right_Forearm_Hand", "primitives": [prim(v_rfa, n_rfa, i_rfa, 0)]})

    # Mesh 6 & 8: Muslos
    v_th, n_th, i_th = combine_prims([make_box([-0.06, -0.38, -0.06], [0.06, 0.0, 0.06]), make_sphere(0.06, (0,0,0), 8, 12)])
    b.meshes.append({"name": "Mesh_Left_Thigh", "primitives": [prim(v_th, n_th, i_th, 0)]})

    # Mesh 7 & 9: Pantorrillas con Pie
    v_calf, n_calf, i_calf = combine_prims([
        make_box([-0.055, -0.38, -0.055], [0.055, 0.0, 0.055]),
        make_sphere(0.055, (0,0,0), 8, 12),
        make_box([-0.055, -0.42, -0.055], [0.055, -0.36, 0.14])
    ])
    b.meshes.append({"name": "Mesh_Left_Calf", "primitives": [prim(v_calf, n_calf, i_calf, 0)]})
    b.meshes.append({"name": "Mesh_Right_Thigh", "primitives": [prim(v_th, n_th, i_th, 0)]})
    b.meshes.append({"name": "Mesh_Right_Calf", "primitives": [prim(v_calf, n_calf, i_calf, 0)]})

    # Mesh 10: Flecha horizontal separada del pecho una longitud de flecha.
    arrow_length = 0.65
    v_arr, n_arr, i_arr = make_chest_directional_arrow(
        length=arrow_length, r_shaft=0.024, r_head=0.068, head_len=0.22)
    b.meshes.append({
        "name": "Mesh_Directional_Arrow_Ahead",
        "primitives": [prim(v_arr, n_arr, i_arr, 7)]
    })
    b.meshes.append({"name": "Mesh_Hiking_Stick", "primitives": [
        prim(v_sw, n_sw, i_sw, 5), prim(v_sm, n_sm, i_sm, 6)
    ]})

    # --- JERARQUÍA DE NODOS ---
    q_id = [0.0, 0.0, 0.0, 1.0]
    b.nodes = [
        # Node 0: Root (aloja al torso y a la flecha fija horizontal)
        {"name": "Pegman_Root", "children": [1, 11], "translation": [0.0, 0.0, 0.0]},
        # Node 1: Torso
        {"name": "Torso", "mesh": 0, "children": [2, 3, 5, 7, 9], "translation": [0.0, 0.81, 0.0], "rotation": q_id},
        # Node 2: Head
        {"name": "Head", "mesh": 1, "translation": [0.0, 0.68, 0.0], "rotation": q_id},
        # Node 3: Left_UpperArm
        {"name": "Left_UpperArm", "mesh": 2, "children": [4], "translation": [-0.22, 0.44, 0.0], "rotation": q_id},
        # Node 4: Left_Forearm
        {"name": "Left_Forearm", "mesh": 3, "children": [12],
         "translation": [0.0, -0.26, 0.0], "rotation": q_id},
        # Node 5: Right_UpperArm
        {"name": "Right_UpperArm", "mesh": 4, "children": [6], "translation": [0.22, 0.44, 0.0], "rotation": q_id},
        # Node 6: Right_Forearm
        {"name": "Right_Forearm", "mesh": 5, "translation": [0.0, -0.26, 0.0], "rotation": q_id},
        # Node 7: Left_Thigh
        {"name": "Left_Thigh", "mesh": 6, "children": [8], "translation": [-0.09, 0.0, 0.0], "rotation": q_id},
        # Node 8: Left_Calf
        {"name": "Left_Calf", "mesh": 7, "translation": [0.0, -0.38, 0.0], "rotation": q_id},
        # Node 9: Right_Thigh
        {"name": "Right_Thigh", "mesh": 8, "children": [10], "translation": [0.09, 0.0, 0.0], "rotation": q_id},
        # Node 10: Right_Calf
        {"name": "Right_Calf", "mesh": 9, "translation": [0.0, -0.38, 0.0], "rotation": q_id},
        # Node 11: el inicio está una longitud de flecha más lejos que antes.
        {"name": "Directional_Arrow", "mesh": 10, "translation": [0.0, 1.14, 0.10 + arrow_length],
         "rotation": q_id, "scale": [0.0, 0.0, 0.0]},
        {"name": "Hiking_Stick", "mesh": 11, "translation": [0.0, 0.0, 0.0],
         "scale": [1.0, 1.0, 1.0]}
    ]

    animation_tracks = {}

    def build_animation(anim_name, times, tracks):
        times = np.asarray(times, dtype=np.float32)
        tracks = {node: dict(paths) for node, paths in tracks.items()}
        # Todos los clips controlan la raíz para que el giro de Desorientado
        # no permanezca activo al pasar a otra animación.
        tracks.setdefault(0, {})
        tracks[0].setdefault("rotation", np.tile(q_id, (len(times), 1)))
        # Los clips sentados estiran ligeramente el brazo de apoyo. Restablecer
        # siempre la escala evita que esta pose se filtre a otro clip.
        for node in (3, 4, 5, 6):
            tracks.setdefault(node, {})
            tracks[node].setdefault("scale", np.ones((len(times), 3), dtype=np.float32))
        tracks.setdefault(12, {})
        tracks[12].setdefault("scale", np.ones((len(times), 3), dtype=np.float32))
        animation_tracks[anim_name] = (times, {
            node: {path: np.asarray(data, dtype=np.float32) for path, data in paths.items()}
            for node, paths in tracks.items()
        })
        time_acc = b.add_time_accessor(times)
        channels, samplers = [], []
        for node_idx, paths in tracks.items():
            for path, data in paths.items():
                is_vec3 = (path == "translation" or path == "scale")
                if is_vec3:
                    out_acc = b.add_vec3_keyframes(np.array(data, dtype=np.float32))
                else:
                    out_acc = b.add_quat_keyframes(np.array(data, dtype=np.float32))
                s_idx = len(samplers)
                samplers.append({"input": time_acc, "interpolation": "LINEAR", "output": out_acc})
                channels.append({"sampler": s_idx, "target": {"node": node_idx, "path": path}})
        b.animations.append({"name": anim_name, "channels": channels, "samplers": samplers})

    def concatenate_animations(name, clip_names, transition=0.2):
        """Concatenate existing clips; interpolate across cuts and close the loop."""
        first_tracks = animation_tracks[clip_names[0]][1]
        combined = {node: {path: [] for path in paths} for node, paths in first_tracks.items()}
        time_parts = []
        end_time = 0.0
        for index, clip_name in enumerate(clip_names):
            clip_times, clip_tracks = animation_tracks[clip_name]
            if index:
                end_time += transition
            time_parts.append(clip_times + end_time)
            for node, paths in combined.items():
                for path in paths:
                    combined[node][path].append(clip_tracks[node][path])
            end_time += float(clip_times[-1])
        time_parts.append(np.array([end_time + transition], dtype=np.float32))
        for node, paths in combined.items():
            for path, segments in paths.items():
                segments.append(first_tracks[node][path][:1])
                combined[node][path] = np.concatenate(segments)
        build_animation(name, np.concatenate(time_parts), combined)

    # ==========================================================================
    # 1. PISTAS DE MARCHA BASE (1.2s, 31 frames)
    # ==========================================================================
    T_walk, N_walk = 1.2, 31
    t_walk = np.linspace(0, T_walk, N_walk, dtype=np.float32)
    ph_w = 2.0 * np.pi * t_walk / T_walk

    torso_trans_w = np.column_stack([0.015 * np.sin(ph_w), 0.82 + 0.022 * (-np.cos(2 * ph_w)), np.zeros_like(t_walk)])
    torso_rot_w, head_rot_w = [], []
    l_ua_w, l_fa_w, r_ua_w_normal, r_fa_w_normal = [], [], [], []
    l_th_w, l_cf_w, r_th_w, r_cf_w = [], [], [], []
    r_ua_w_point, r_fa_w_point = [], []
    beam_scale_hidden_w = np.zeros((N_walk, 3), dtype=np.float32)
    beam_scale_active_w = []

    for p in ph_w:
        # Cadencia del torso
        q_t = quat_mul(axis_angle_to_quat([0, 1, 0], np.radians(3.5) * np.sin(p)),
                       quat_mul(axis_angle_to_quat([1, 0, 0], np.radians(-7.0)),
                                axis_angle_to_quat([0, 0, 1], np.radians(2.0) * np.cos(p))))
        torso_rot_w.append((q_t / np.linalg.norm(q_t)).tolist())
        head_rot_w.append(axis_angle_to_quat([0, 1, 0], np.radians(-1.5) * np.sin(p)).tolist())

        # Piernas con zancada alternativa y flexión de rodilla
        l_th_w.append(axis_angle_to_quat([1, 0, 0], np.radians(-24.0 * np.cos(p))).tolist())
        r_th_w.append(axis_angle_to_quat([1, 0, 0], np.radians(-24.0 * np.cos(p + np.pi))).tolist())

        def knee_f(ph):
            m = ph % (2 * np.pi)
            return np.radians(5.0 + 38.0 * np.exp(-((m - 1.35 * np.pi) / 0.6)**2) + 5.0 * np.exp(-((m - 0.2 * np.pi) / 0.4)**2))
        l_cf_w.append(axis_angle_to_quat([1, 0, 0], knee_f(p)).tolist())
        r_cf_w.append(axis_angle_to_quat([1, 0, 0], knee_f(p + np.pi)).tolist())

        # Brazo izquierdo con bastón
        l_sh = np.radians(-8.0 + 16.0 * np.cos(p))
        q_lua = quat_mul(axis_angle_to_quat([0, 0, 1], np.radians(-6.0)), axis_angle_to_quat([1, 0, 0], l_sh))
        l_ua_w.append(q_lua.tolist())
        l_fa_w.append(axis_angle_to_quat([1, 0, 0], np.radians(-24.0 + 12.0 * np.cos(p))).tolist())

        # Brazo derecho en balanceo de marcha común
        r_sh = np.radians(-6.0 - 18.0 * np.cos(p))
        q_rua = quat_mul(axis_angle_to_quat([0, 0, 1], np.radians(6.0)), axis_angle_to_quat([1, 0, 0], r_sh))
        r_ua_w_normal.append(q_rua.tolist())
        r_fa_w_normal.append(axis_angle_to_quat([1, 0, 0], np.radians(-32.0 - 14.0 * np.cos(p))).tolist())

        # Brazo derecho elevado apuntando al frente durante la marcha
        sh_p_w = np.radians(-84.0 - 2.5 * np.cos(2 * p))
        sh_y_w = np.radians(-2.5 * np.sin(p))
        q_sh_wp = quat_mul(axis_angle_to_quat([0, 1, 0], sh_y_w), axis_angle_to_quat([1, 0, 0], sh_p_w))
        r_ua_w_point.append((q_sh_wp / np.linalg.norm(q_sh_wp)).tolist())
        r_fa_w_point.append(axis_angle_to_quat([1, 0, 0], np.radians(-14.0 + 8.0 * np.cos(2 * p))).tolist())

        # Pulso sutil de longitud en la flecha fija (escala X e Y fijas en 1.0, solo oscila Z)
        pulse_z = 1.0 + 0.15 * np.cos(2 * p)
        beam_scale_active_w.append([1.0, 1.0, pulse_z])

    # 1. Animación: Caminando
    build_animation("Caminando", t_walk, {
        1: {"translation": torso_trans_w, "rotation": torso_rot_w},
        2: {"rotation": head_rot_w},
        3: {"rotation": l_ua_w},
        4: {"rotation": l_fa_w},
        5: {"rotation": r_ua_w_normal},
        6: {"rotation": r_fa_w_normal},
        7: {"rotation": l_th_w},
        8: {"rotation": l_cf_w},
        9: {"rotation": r_th_w},
        10: {"rotation": r_cf_w},
        11: {"scale": beam_scale_hidden_w}
    })

    # ==========================================================================
    # 2. ANIMACIÓN: Descansando (2.5s)
    # ==========================================================================
    T_rest, N_rest = 2.5, 31
    t_rest = np.linspace(0, T_rest, N_rest, dtype=np.float32)
    ph_r = 2.0 * np.pi * t_rest / T_rest

    torso_trans_r = np.column_stack([np.zeros_like(t_rest), 0.81 + 0.003 * np.sin(ph_r), np.zeros_like(t_rest)])
    torso_rot_r, head_rot_r = [], []
    l_ua_r, l_fa_r, r_ua_r, r_fa_r = [], [], [], []
    l_th_r, l_cf_r, r_th_r, r_cf_r = [], [], [], []
    beam_scale_hidden_r = np.zeros((N_rest, 3), dtype=np.float32)

    q_lua_rest_base = quat_mul(axis_angle_to_quat([0, 0, 1], np.radians(-5.0)), axis_angle_to_quat([1, 0, 0], np.radians(-5.0)))
    q_lfa_rest_base = axis_angle_to_quat([1, 0, 0], np.radians(-25.0))
    q_rua_jarras_base = np.array([-0.07789, 0.0, 0.29911, 0.95103], dtype=np.float32)
    q_rfa_jarras_base = np.array([0.04162, 0.0, -0.65110, 0.75785], dtype=np.float32)

    q_lt_rest = axis_angle_to_quat([1, 0, 0], 0.0)
    q_lc_rest = axis_angle_to_quat([1, 0, 0], 0.0)
    q_rt_rest = axis_angle_to_quat([1, 0, 0], np.radians(-8.0))
    q_rc_rest = axis_angle_to_quat([1, 0, 0], np.radians(14.0))

    for p in ph_r:
        torso_rot_r.append(axis_angle_to_quat([1, 0, 0], np.radians(-0.6 * np.sin(p))).tolist())
        head_rot_r.append(axis_angle_to_quat([1, 0, 0], np.radians(0.3 * np.sin(p))).tolist())

        q_l = quat_mul(axis_angle_to_quat([0, 0, 1], np.radians(0.4 * np.sin(p))), q_lua_rest_base)
        l_ua_r.append((q_l / np.linalg.norm(q_l)).tolist())
        l_fa_r.append(q_lfa_rest_base.tolist())

        q_r = quat_mul(axis_angle_to_quat([0, 0, 1], np.radians(-0.4 * np.sin(p))), q_rua_jarras_base)
        r_ua_r.append((q_r / np.linalg.norm(q_r)).tolist())
        r_fa_r.append(q_rfa_jarras_base.tolist())

        l_th_r.append(q_lt_rest.tolist())
        l_cf_r.append(q_lc_rest.tolist())
        r_th_r.append(q_rt_rest.tolist())
        r_cf_r.append(q_rc_rest.tolist())

    build_animation("Descansando", t_rest, {
        1: {"translation": torso_trans_r, "rotation": torso_rot_r},
        2: {"rotation": head_rot_r},
        3: {"rotation": l_ua_r},
        4: {"rotation": l_fa_r},
        5: {"rotation": r_ua_r},
        6: {"rotation": r_fa_r},
        7: {"rotation": l_th_r},
        8: {"rotation": l_cf_r},
        9: {"rotation": r_th_r},
        10: {"rotation": r_cf_r},
        11: {"scale": beam_scale_hidden_r}
    })

    # ==========================================================================
    # 3. PISTAS DE APUNTADO ESTÁTICO (1.0s, 31 frames)
    # ==========================================================================
    T_point, N_point = 1.0, 31
    t_point = np.linspace(0, T_point, N_point, dtype=np.float32)
    ph_p = 2.0 * np.pi * t_point / T_point

    torso_trans_p = np.column_stack([np.zeros_like(t_point), np.full_like(t_point, 0.81), np.zeros_like(t_point)])
    torso_rot_p, head_rot_p = [], []
    l_ua_p, l_fa_p, r_ua_p, r_fa_p = [], [], [], []
    l_th_p, l_cf_p, r_th_p, r_cf_p = [], [], [], []
    beam_scale_hidden_p = np.zeros((N_point, 3), dtype=np.float32)
    beam_scale_active_p = []

    for p in ph_p:
        q_tp = quat_mul(axis_angle_to_quat([0, 1, 0], np.radians(2.0) * np.sin(p)),
                        axis_angle_to_quat([1, 0, 0], np.radians(-5.0 - 1.5 * np.cos(2 * p))))
        torso_rot_p.append((q_tp / np.linalg.norm(q_tp)).tolist())
        head_rot_p.append(axis_angle_to_quat([1, 0, 0], np.radians(-2.0)).tolist())

        sh_pitch = np.radians(-84.0 - 3.0 * np.cos(2 * p))
        sh_yaw = np.radians(-3.0 * np.sin(p))
        q_sh = quat_mul(axis_angle_to_quat([0, 1, 0], sh_yaw), axis_angle_to_quat([1, 0, 0], sh_pitch))
        r_ua_p.append((q_sh / np.linalg.norm(q_sh)).tolist())

        elbow_pitch = np.radians(-14.0 + 10.0 * np.cos(2 * p))
        r_fa_p.append(axis_angle_to_quat([1, 0, 0], elbow_pitch).tolist())

        l_ua_p.append(q_lua_rest_base.tolist())
        l_fa_p.append(q_lfa_rest_base.tolist())

        l_th_p.append(axis_angle_to_quat([1, 0, 0], np.radians(-8.0)).tolist())
        l_cf_p.append(axis_angle_to_quat([1, 0, 0], np.radians(10.0)).tolist())
        r_th_p.append(axis_angle_to_quat([1, 0, 0], np.radians(8.0)).tolist())
        r_cf_p.append(axis_angle_to_quat([1, 0, 0], np.radians(6.0)).tolist())

        # Pulso sutil de longitud de la flecha adelantada (+Z)
        pulse_z_stat = 1.0 + 0.15 * np.cos(2 * p)
        beam_scale_active_p.append([1.0, 1.0, pulse_z_stat])

    # 3. Animación: Apuntando (dedo índice)
    build_animation("Apuntando", t_point, {
        1: {"translation": torso_trans_p, "rotation": torso_rot_p},
        2: {"rotation": head_rot_p},
        3: {"rotation": l_ua_p},
        4: {"rotation": l_fa_p},
        5: {"rotation": r_ua_p},
        6: {"rotation": r_fa_p},
        7: {"rotation": l_th_p},
        8: {"rotation": l_cf_p},
        9: {"rotation": r_th_p},
        10: {"rotation": r_cf_p},
        11: {"scale": beam_scale_hidden_p}
    })

    # ==========================================================================
    # 4. ANIMACIÓN: Desorientado (Travolta Meme - 3.6s)
    # ==========================================================================
    T_travolta, N_travolta = 3.6, 37
    t_travolta = np.linspace(0, T_travolta, N_travolta, dtype=np.float32)
    ph_tr = 2.0 * np.pi * t_travolta / T_travolta

    torso_trans_tr = np.column_stack([0.025 * np.cos(ph_tr), 0.81 + 0.005 * np.cos(2 * ph_tr), -0.01 * np.cos(ph_tr)])
    root_rot_tr, torso_rot_tr, head_rot_tr = [], [], []
    l_ua_tr, l_fa_tr, r_ua_tr, r_fa_tr = [], [], [], []
    l_th_tr, l_cf_tr, r_th_tr, r_cf_tr = [], [], [], []
    beam_scale_hidden_tr = np.zeros((N_travolta, 3), dtype=np.float32)

    for p in ph_tr:
        # El cuerpo completo barre ambos lados; los demás clips recuperan la
        # orientación neutra mediante su pista de rotación de la raíz.
        root_rot_tr.append(axis_angle_to_quat([0, 1, 0], np.radians(34.0 * np.cos(p))).tolist())
        torso_y = np.radians(16.0 * np.cos(p) - 3.0 * np.sin(2 * p))
        torso_p = np.radians(-4.0 + 1.5 * np.cos(p))
        torso_r = np.radians(-2.5 * np.sin(p))
        q_tt = quat_mul(axis_angle_to_quat([0, 1, 0], torso_y),
                        quat_mul(axis_angle_to_quat([1, 0, 0], torso_p),
                                 axis_angle_to_quat([0, 0, 1], torso_r)))
        torso_rot_tr.append((q_tt / np.linalg.norm(q_tt)).tolist())

        head_y = np.radians(28.0 * np.cos(p) - 5.0 * np.sin(2 * p))
        head_p = np.radians(-2.0 + 3.0 * np.sin(p))
        head_r = np.radians(5.0 * np.sin(p))
        q_ht = quat_mul(axis_angle_to_quat([0, 1, 0], head_y),
                        quat_mul(axis_angle_to_quat([1, 0, 0], head_p),
                                 axis_angle_to_quat([0, 0, 1], head_r)))
        head_rot_tr.append((q_ht / np.linalg.norm(q_ht)).tolist())

        r_sh_p = np.radians(-32.0 - 22.0 * np.cos(p))
        r_sh_y = np.radians(22.0 * np.cos(p))
        r_sh_r = np.radians(24.0 + 16.0 * np.sin(p))
        q_rua = quat_mul(axis_angle_to_quat([0, 1, 0], r_sh_y),
                         quat_mul(axis_angle_to_quat([1, 0, 0], r_sh_p),
                                  axis_angle_to_quat([0, 0, 1], r_sh_r)))
        r_ua_tr.append((q_rua / np.linalg.norm(q_rua)).tolist())
        r_fa_tr.append(axis_angle_to_quat([1, 0, 0], np.radians(-62.0 - 21.0 * np.cos(p))).tolist())

        l_sh_p = np.radians(-20.0 + 18.0 * np.cos(p))
        l_sh_r = np.radians(-22.0 - 13.0 * np.sin(p))
        q_lua = quat_mul(axis_angle_to_quat([0, 0, 1], l_sh_r), axis_angle_to_quat([1, 0, 0], l_sh_p))
        l_ua_tr.append((q_lua / np.linalg.norm(q_lua)).tolist())
        l_fa_tr.append(axis_angle_to_quat([1, 0, 0], np.radians(-32.0 - 14.0 * np.cos(p))).tolist())

        r_th_tr.append(axis_angle_to_quat([1, 0, 0], np.radians(-6.0 + 4.0 * np.cos(p))).tolist())
        r_cf_tr.append(axis_angle_to_quat([1, 0, 0], np.radians(8.0 - 4.0 * np.cos(p))).tolist())
        l_th_tr.append(axis_angle_to_quat([1, 0, 0], np.radians(-4.0 - 4.0 * np.cos(p))).tolist())
        l_cf_tr.append(axis_angle_to_quat([1, 0, 0], np.radians(12.0 + 6.0 * np.cos(p))).tolist())

    build_animation("Desorientado", t_travolta, {
        0: {"rotation": root_rot_tr},
        1: {"translation": torso_trans_tr, "rotation": torso_rot_tr},
        2: {"rotation": head_rot_tr},
        3: {"rotation": l_ua_tr},
        4: {"rotation": l_fa_tr},
        5: {"rotation": r_ua_tr},
        6: {"rotation": r_fa_tr},
        7: {"rotation": l_th_tr},
        8: {"rotation": l_cf_tr},
        9: {"rotation": r_th_tr},
        10: {"rotation": r_cf_tr},
        11: {"scale": beam_scale_hidden_tr}
    })

    # ==========================================================================
    # 5. ANIMACIÓN: Caminando_Apuntando (1.2s - dedo)
    # ==========================================================================
    build_animation("Caminando_Apuntando", t_walk, {
        1: {"translation": torso_trans_w, "rotation": torso_rot_w},
        2: {"rotation": head_rot_w},
        3: {"rotation": l_ua_w},
        4: {"rotation": l_fa_w},
        5: {"rotation": r_ua_w_point},
        6: {"rotation": r_fa_w_point},
        7: {"rotation": l_th_w},
        8: {"rotation": l_cf_w},
        9: {"rotation": r_th_w},
        10: {"rotation": r_cf_w},
        11: {"scale": beam_scale_hidden_w}
    })

    # ==========================================================================
    # 6. ANIMACIÓN: Caminando_Apuntando_Flecha (1.2s - flecha activa)
    # ==========================================================================
    build_animation("Caminando_Apuntando_Flecha", t_walk, {
        1: {"translation": torso_trans_w, "rotation": torso_rot_w},
        2: {"rotation": head_rot_w},
        3: {"rotation": l_ua_w},
        4: {"rotation": l_fa_w},
        5: {"rotation": r_ua_w_point},
        6: {"rotation": r_fa_w_point},
        7: {"rotation": l_th_w},
        8: {"rotation": l_cf_w},
        9: {"rotation": r_th_w},
        10: {"rotation": r_cf_w},
        11: {"scale": beam_scale_active_w}
    })

    # ==========================================================================
    # 7. ANIMACIÓN: Apuntando_Flecha (1.0s - estático + flecha activa)
    # ==========================================================================
    build_animation("Apuntando_Flecha", t_point, {
        1: {"translation": torso_trans_p, "rotation": torso_rot_p},
        2: {"rotation": head_rot_p},
        3: {"rotation": l_ua_p},
        4: {"rotation": l_fa_p},
        5: {"rotation": r_ua_p},
        6: {"rotation": r_fa_p},
        7: {"rotation": l_th_p},
        8: {"rotation": l_cf_p},
        9: {"rotation": r_th_p},
        10: {"rotation": r_cf_p},
        11: {"scale": beam_scale_active_p}
    })

    # La pista de distancia sin rumbo combina tres pasos completos con un ciclo
    # de desorientación, conservando los siete clips originales del modelo.
    t_mixed = np.linspace(0, T_travolta, 91, dtype=np.float32)
    walk_time = np.mod(t_mixed, T_walk)

    def sample_track(values, source_times, target_times, rotation=False):
        values = np.asarray(values, dtype=np.float32)
        result = np.column_stack([
            np.interp(target_times, source_times, values[:, component])
            for component in range(values.shape[1])
        ]).astype(np.float32)
        if rotation:
            result /= np.linalg.norm(result, axis=1, keepdims=True)
        return result

    def walk(values, rotation=False):
        return sample_track(values, t_walk, walk_time, rotation)

    def confused(values, rotation=False):
        return sample_track(values, t_travolta, t_mixed, rotation)

    build_animation("Caminando_Desorientado", t_mixed, {
        0: {"rotation": confused(root_rot_tr, True)},
        1: {"translation": walk(torso_trans_w), "rotation": confused(torso_rot_tr, True)},
        2: {"rotation": confused(head_rot_tr, True)},
        3: {"rotation": confused(l_ua_tr, True)},
        4: {"rotation": confused(l_fa_tr, True)},
        5: {"rotation": confused(r_ua_tr, True)},
        6: {"rotation": confused(r_fa_tr, True)},
        7: {"rotation": walk(l_th_w, True)},
        8: {"rotation": walk(l_cf_w, True)},
        9: {"rotation": walk(r_th_w, True)},
        10: {"rotation": walk(r_cf_w, True)},
        11: {"scale": np.zeros((len(t_mixed), 3), dtype=np.float32)}
    })

    concatenate_animations("buscando", ["Caminando", "Descansando", "Desorientado"])
    concatenate_animations("buscando_brújula", ["Caminando_Apuntando_Flecha", "Apuntando_Flecha"])

    # La referencia es una pose sentada asimétrica. La cadera toca el suelo,
    # el brazo izquierdo se apoya detrás y a la izquierda, y el derecho saluda.
    # Todas las pistas usan los mismos nodos para poder concatenarse sin saltos.
    def rest_pose():
        return {node: {path: values[0].copy() for path, values in paths.items()}
                for node, paths in animation_tracks["Descansando"][1].items()}

    def seated_pose(height=0.0, forward=-0.09, lean=-10.0, wave=0.0, look=0.0):
        pose = rest_pose()
        def joint(node, degrees, axis=(1, 0, 0)):
            pose[node]["rotation"] = axis_angle_to_quat(axis, np.radians(degrees))
        pose[1]["translation"] = np.array([0.0, height, forward], dtype=np.float32)
        joint(1, lean)
        joint(2, -18.0 + look)
        # La cadera está en y=0. El muslo izquierdo alza la rodilla y la
        # pantorrilla regresa al suelo; la pierna derecha queda extendida.
        joint(7, -120.0)
        joint(8, 67.0)
        joint(9, -90.0)
        joint(10, 0.0)
        # Hombro izquierdo: x=-0.22. El giro lateral lleva la mano hasta
        # x≈-0.50, y≈0.04, z≈-0.36, contando el desplazamiento del torso.
        # Su esfera de 4.5 cm apoya en y=0; no hay estiramiento artificial.
        pose[3]["rotation"] = quat_mul(
            axis_angle_to_quat([0, 0, 1], np.radians(-38.0)),
            axis_angle_to_quat([1, 0, 0], np.radians(30.0)))
        joint(4, 0.0)
        pose[12]["scale"] = np.zeros(3, dtype=np.float32)
        # El brazo derecho saluda por encima de la cabeza; hombro y codo
        # oscilan mientras la cabeza mira al cielo durante diez segundos.
        pose[5]["rotation"] = quat_mul(
            axis_angle_to_quat([0, 0, 1], np.radians(12.0 + wave)),
            axis_angle_to_quat([1, 0, 0], np.radians(-130.0)))
        joint(6, -10.0 + 0.7 * wave)
        pose[11]["scale"] = np.zeros(3, dtype=np.float32)
        return pose

    def interpolate_pose(start, end, fraction):
        """Smoothstep translation/scale and shortest-arc quaternion interpolation."""
        fraction = np.clip(fraction, 0.0, 1.0)
        fraction = fraction * fraction * (3.0 - 2.0 * fraction)
        result = {}
        for node, paths in start.items():
            result[node] = {}
            for path, first in paths.items():
                last = end[node][path]
                if path == "rotation":
                    dot = float(np.dot(first, last))
                    if dot < 0.0:
                        last = -last
                        dot = -dot
                    if dot > 0.9995:
                        value = first + fraction * (last - first)
                    else:
                        arc = np.arccos(np.clip(dot, -1.0, 1.0))
                        value = (np.sin((1.0 - fraction) * arc) * first +
                                 np.sin(fraction * arc) * last) / np.sin(arc)
                    result[node][path] = value / np.linalg.norm(value)
                else:
                    result[node][path] = first + fraction * (last - first)
        return result

    def build_pose_clip(name, times, pose_at):
        """Sample a coherent full-body pose on a common timeline."""
        poses = [pose_at(float(t)) for t in times]
        tracks = {node: {path: np.asarray([pose[node][path] for pose in poses], dtype=np.float32)
                         for path in paths} for node, paths in poses[0].items()}
        build_animation(name, times, tracks)

    standing = rest_pose()
    # Flexionar primero las rodillas, sin bajar aún la cadera: así los pies
    # se despegan ligeramente del suelo en vez de atravesarlo al agacharse.
    knees_bent = rest_pose()
    prebent = rest_pose()
    # La ligera elevación inicial de talones y cadera compensa el barrido de
    # la puntera del modelo al flexionar; después comienza el descenso.
    knees_bent[1]["translation"][1] = 0.86
    prebent[1]["translation"][1] = 0.86
    for thigh, calf in ((7, 8), (9, 10)):
        knees_bent[calf]["rotation"] = axis_angle_to_quat([1, 0, 0], np.radians(60.0))
        prebent[thigh]["rotation"] = axis_angle_to_quat([1, 0, 0], np.radians(-25.0))
        prebent[calf]["rotation"] = axis_angle_to_quat([1, 0, 0], np.radians(60.0))

    crouched = seated_pose(height=0.67, forward=0.08, lean=20.0)
    crouched[2]["rotation"] = axis_angle_to_quat([1, 0, 0], np.radians(-15.0))
    for thigh, calf in ((7, 8), (9, 10)):
        crouched[thigh]["rotation"] = axis_angle_to_quat([1, 0, 0], np.radians(-65.0))
        crouched[calf]["rotation"] = axis_angle_to_quat([1, 0, 0], np.radians(110.0))
    crouched[3]["rotation"] = axis_angle_to_quat([1, 0, 0], np.radians(35.0))
    crouched[4]["rotation"] = axis_angle_to_quat([1, 0, 0], np.radians(-15.0))
    crouched[5]["rotation"] = q_rua_jarras_base
    crouched[6]["rotation"] = q_rfa_jarras_base
    # Girar las piernas a la postura final antes del último descenso evita
    # que las pantorrillas barran el suelo mientras cae la cadera.
    ready_to_sit = seated_pose(height=0.56)
    sitting = seated_pose()

    def descending(t):
        if t < 0.25:
            return interpolate_pose(standing, knees_bent, t / 0.25)
        if t < 0.5:
            return interpolate_pose(knees_bent, prebent, (t - 0.25) / 0.25)
        if t < 1.15:
            return interpolate_pose(prebent, crouched, (t - 0.5) / 0.65)
        if t < 1.75:
            return interpolate_pose(crouched, ready_to_sit, (t - 1.15) / 0.6)
        return interpolate_pose(ready_to_sit, sitting, (t - 1.75) / 0.65)

    t_sit = np.linspace(0.0, 2.4, 49, dtype=np.float32)
    build_pose_clip("sentanse", t_sit, descending)

    def greeting(t):
        phase = 2.0 * np.pi * t / 1.25
        return seated_pose(wave=11.0 * np.sin(phase), look=-2.5 * np.sin(phase / 2.0))

    t_greet = np.linspace(0.0, 10.0, 201, dtype=np.float32)
    build_pose_clip("Sentado saludando", t_greet, greeting)

    def ascending(t):
        return descending(2.4 - t)

    t_stand = np.linspace(0.0, 2.4, 49, dtype=np.float32)
    build_pose_clip("Levantarse", t_stand, ascending)
    concatenate_animations("sentarysaludar",
                           ["sentanse", "Sentado saludando", "Levantarse"])

    b.build_glb(output_filename)

if __name__ == "__main__":
    generate_pegman_hiker_fixed_arrow()
