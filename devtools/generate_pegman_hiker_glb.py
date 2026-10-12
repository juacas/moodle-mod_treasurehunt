import json
import struct
import numpy as np

# --- UTILIDADES MATEMÁTICAS ---
def axis_angle_to_quat(axis, angle_rad):
    axis = np.array(axis, dtype=np.float32)
    axis = axis / np.linalg.norm(axis)
    s = np.sin(angle_rad / 2.0)
    c = np.cos(angle_rad / 2.0)
    return np.array([axis[0] * s, axis[1] * s, axis[2] * s, c], dtype=np.float32)

def quat_mul(q1, q2):
    x1, y1, z1, w1 = q1
    x2, y2, z2, w2 = q2
    return np.array([
        w1*x2 + x1*w2 + y1*z2 - z1*y2,
        w1*y2 - x1*z2 + y1*w2 + z1*x2,
        w1*z2 + x1*y2 - y1*x2 + z1*w2,
        w1*w2 - x1*x2 - y1*y2 - z1*z2
    ], dtype=np.float32)

def combine_prims(parts):
    all_v, all_n, all_i = [], [], []
    offset = 0
    for v, n, i in parts:
        all_v.append(v)
        all_n.append(n)
        all_i.append(i + offset)
        offset += len(v)
    return np.vstack(all_v), np.vstack(all_n), np.concatenate(all_i)

# --- GENERADORES DE GEOMETRÍA ---
def make_box(min_pt, max_pt):
    x0, y0, z0 = min_pt
    x1, y1, z1 = max_pt
    faces = [
        ([[x0, y0, z1], [x1, y0, z1], [x1, y1, z1], [x0, y1, z1]], [0.0, 0.0, 1.0]),
        ([[x1, y0, z0], [x0, y0, z0], [x0, y1, z0], [x1, y1, z0]], [0.0, 0.0, -1.0]),
        ([[x0, y1, z1], [x1, y1, z1], [x1, y1, z0], [x0, y1, z0]], [0.0, 1.0, 0.0]),
        ([[x0, y0, z0], [x1, y0, z0], [x1, y0, z1], [x0, y0, z1]], [0.0, -1.0, 0.0]),
        ([[x1, y0, z1], [x1, y0, z0], [x1, y1, z0], [x1, y1, z1]], [1.0, 0.0, 0.0]),
        ([[x0, y0, z0], [x0, y0, z1], [x0, y1, z1], [x0, y1, z0]], [-1.0, 0.0, 0.0]),
    ]
    verts, norms, idxs = [], [], []
    for quad, norm in faces:
        base = len(verts)
        for p in quad:
            verts.append(p)
            norms.append(norm)
        idxs.extend([base, base + 1, base + 2, base, base + 2, base + 3])
    return np.array(verts, dtype=np.float32), np.array(norms, dtype=np.float32), np.array(idxs, dtype=np.uint16)

def make_sphere(radius, center=(0,0,0), lats=14, lons=20):
    cx, cy, cz = center
    verts, norms, idxs = [], [], []
    for i in range(lats + 1):
        phi = np.pi * i / lats
        s_phi, c_phi = np.sin(phi), np.cos(phi)
        for j in range(lons + 1):
            theta = 2 * np.pi * j / lons
            s_th, c_th = np.sin(theta), np.cos(theta)
            nx = s_phi * s_th
            ny = c_phi
            nz = s_phi * c_th
            verts.append([cx + radius * nx, cy + radius * ny, cz + radius * nz])
            norms.append([nx, ny, nz])
    for i in range(lats):
        for j in range(lons):
            f = i * (lons + 1) + j
            s = f + lons + 1
            idxs.extend([f, s, f + 1, s, s + 1, f + 1])
    return np.array(verts, dtype=np.float32), np.array(norms, dtype=np.float32), np.array(idxs, dtype=np.uint16)

def make_cylinder_x(radius, length, center=(0,0,0), segments=16):
    cx, cy, cz = center
    theta = np.linspace(0, 2*np.pi, segments, endpoint=False)
    c_th, s_th = np.cos(theta), np.sin(theta)
    hl = length / 2.0
    verts, norms, idxs = [], [], []
    c1 = len(verts); verts.append([cx + hl, cy, cz]); norms.append([1.0, 0.0, 0.0])
    for i in range(segments):
        verts.append([cx + hl, cy + radius * c_th[i], cz + radius * s_th[i]])
        norms.append([1.0, 0.0, 0.0])
        idxs.extend([c1, c1 + 1 + i, c1 + 1 + ((i + 1) % segments)])
    c2 = len(verts); verts.append([cx - hl, cy, cz]); norms.append([-1.0, 0.0, 0.0])
    for i in range(segments):
        verts.append([cx - hl, cy + radius * c_th[i], cz + radius * s_th[i]])
        norms.append([-1.0, 0.0, 0.0])
        idxs.extend([c2, c2 + 1 + ((i + 1) % segments), c2 + 1 + i])
    for i in range(segments):
        nxt = (i + 1) % segments
        b = len(verts)
        p0 = [cx - hl, cy + radius * c_th[i], cz + radius * s_th[i]]
        p1 = [cx + hl, cy + radius * c_th[i], cz + radius * s_th[i]]
        p2 = [cx + hl, cy + radius * c_th[nxt], cz + radius * s_th[nxt]]
        p3 = [cx - hl, cy + radius * c_th[nxt], cz + radius * s_th[nxt]]
        n = [0.0, c_th[i], s_th[i]]
        verts.extend([p0, p1, p2, p3])
        norms.extend([n, n, [0.0, c_th[nxt], s_th[nxt]], [0.0, c_th[nxt], s_th[nxt]]])
        idxs.extend([b, b + 1, b + 2, b, b + 2, b + 3])
    return np.array(verts, dtype=np.float32), np.array(norms, dtype=np.float32), np.array(idxs, dtype=np.uint16)

def make_cylinder_between_points(p0, p1, radius, segments=12):
    p0 = np.array(p0, dtype=np.float32)
    p1 = np.array(p1, dtype=np.float32)
    v_dir = p1 - p0
    length = np.linalg.norm(v_dir)
    dir_unit = v_dir / length
    perp = np.cross(dir_unit, [1.0, 0.0, 0.0] if abs(dir_unit[0]) < 0.9 else [0.0, 1.0, 0.0])
    perp = perp / np.linalg.norm(perp)
    perp2 = np.cross(dir_unit, perp)
    theta = np.linspace(0, 2*np.pi, segments, endpoint=False)
    c_th, s_th = np.cos(theta), np.sin(theta)
    verts, norms, idxs = [], [], []
    c0 = len(verts); verts.append(p0.tolist()); norms.append((-dir_unit).tolist())
    for i in range(segments):
        pt = p0 + radius * (c_th[i] * perp + s_th[i] * perp2)
        verts.append(pt.tolist())
        norms.append((-dir_unit).tolist())
        idxs.extend([c0, c0 + 1 + ((i + 1) % segments), c0 + 1 + i])
    c1 = len(verts); verts.append(p1.tolist()); norms.append(dir_unit.tolist())
    for i in range(segments):
        pt = p1 + radius * (c_th[i] * perp + s_th[i] * perp2)
        verts.append(pt.tolist())
        norms.append(dir_unit.tolist())
        idxs.extend([c1, c1 + 1 + i, c1 + 1 + ((i + 1) % segments)])
    for i in range(segments):
        nxt = (i + 1) % segments
        b = len(verts)
        v0 = p0 + radius * (c_th[i] * perp + s_th[i] * perp2)
        v1 = p1 + radius * (c_th[i] * perp + s_th[i] * perp2)
        v2 = p1 + radius * (c_th[nxt] * perp + s_th[nxt] * perp2)
        v3 = p0 + radius * (c_th[nxt] * perp + s_th[nxt] * perp2)
        n0 = (c_th[i] * perp + s_th[i] * perp2).tolist()
        n1 = (c_th[nxt] * perp + s_th[nxt] * perp2).tolist()
        verts.extend([v0.tolist(), v1.tolist(), v2.tolist(), v3.tolist()])
        norms.extend([n0, n0, n1, n1])
        idxs.extend([b, b + 1, b + 2, b, b + 2, b + 3])
    return np.array(verts, dtype=np.float32), np.array(norms, dtype=np.float32), np.array(idxs, dtype=np.uint16)

# --- CLASE ENSAMBLADORA GLTF/GLB ---
class GLBBuilder:
    def __init__(self):
        self.bin_bytes = bytearray()
        self.buffer_views = []
        self.accessors = []
        self.materials = []
        self.meshes = []
        self.nodes = []
        self.animations = []

    def add_buffer_view(self, data_bytes, target=None):
        offset = len(self.bin_bytes)
        self.bin_bytes.extend(data_bytes)
        pad = (4 - len(self.bin_bytes) % 4) % 4
        self.bin_bytes.extend(b'\x00' * pad)
        bv = {"buffer": 0, "byteOffset": offset, "byteLength": len(data_bytes)}
        if target: bv["target"] = target
        idx = len(self.buffer_views)
        self.buffer_views.append(bv)
        return idx

    def add_accessor(self, bv_idx, component_type, count, type_str, min_val, max_val):
        acc = {"bufferView": bv_idx, "byteOffset": 0, "componentType": component_type, "count": count, "type": type_str, "min": min_val, "max": max_val}
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
        gltf_dict = {
            "asset": {"version": "2.0", "generator": "Cesium Hiker Walk Generator"},
            "scenes": [{"nodes": [0]}],
            "nodes": self.nodes,
            "meshes": self.meshes,
            "materials": self.materials,
            "animations": self.animations,
            "buffers": [{"byteLength": len(self.bin_bytes)}],
            "bufferViews": self.buffer_views,
            "accessors": self.accessors
        }
        json_bytes = json.dumps(gltf_dict, separators=(',', ':')).encode('utf-8')
        pad_j = (4 - len(json_bytes) % 4) % 4
        json_bytes += b' ' * pad_j
        total_len = 12 + 8 + len(json_bytes) + 8 + len(self.bin_bytes)
        header = struct.pack('<4sII', b'glTF', 2, total_len)
        chunk0 = struct.pack('<II', len(json_bytes), 0x4E4F534A)
        chunk1 = struct.pack('<II', len(self.bin_bytes), 0x004E4942)
        with open(filename, 'wb') as f:
            f.write(header + chunk0 + json_bytes + chunk1 + bytes(self.bin_bytes))
        print(f"Archivo exportado con éxito: {filename} ({len(self.bin_bytes)} bytes)")

# --- CONSTRUCCIÓN DEL MODELO ---
def generate_hiker():
    builder = GLBBuilder()
    builder.materials = [
        {"name": "Mat_Yellow", "pbrMetallicRoughness": {"baseColorFactor": [0.976, 0.706, 0.106, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.75}, "doubleSided": True},
        {"name": "Mat_Amber", "pbrMetallicRoughness": {"baseColorFactor": [0.722, 0.388, 0.078, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.85}, "doubleSided": True},
        {"name": "Mat_Backpack", "pbrMetallicRoughness": {"baseColorFactor": [0.620, 0.482, 0.337, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.9}, "doubleSided": True},
        {"name": "Mat_MatGreen", "pbrMetallicRoughness": {"baseColorFactor": [0.263, 0.651, 0.220, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.85}, "doubleSided": True},
        {"name": "Mat_Straps", "pbrMetallicRoughness": {"baseColorFactor": [0.239, 0.180, 0.125, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.95}, "doubleSided": True},
        {"name": "Mat_StickWood", "pbrMetallicRoughness": {"baseColorFactor": [0.369, 0.227, 0.118, 1.0], "metallicFactor": 0.0, "roughnessFactor": 0.9}, "doubleSided": True},
        {"name": "Mat_StickMetal", "pbrMetallicRoughness": {"baseColorFactor": [0.627, 0.647, 0.667, 1.0], "metallicFactor": 0.15, "roughnessFactor": 0.7}, "doubleSided": True}
    ]

    def add_prim(v, n, idx, mat_idx):
        return {"attributes": {"POSITION": builder.add_vec3_floats(v), "NORMAL": builder.add_vec3_floats(n)}, "indices": builder.add_indices(idx), "material": mat_idx}

    # Torso + Cabeza + Mochila + Esterilla + Correas
    vy, ny, iy = combine_prims([
        make_box([-0.17, 0.0, -0.09], [0.17, 0.48, -0.07]),
        make_box([-0.17, 0.0, -0.07], [-0.14, 0.48, 0.09]),
        make_box([0.14, 0.0, -0.07], [0.17, 0.48, 0.09]),
        make_box([-0.14, 0.0, -0.07], [0.14, 0.02, 0.09]),
        make_box([-0.14, 0.46, -0.07], [0.14, 0.48, 0.02]),
        make_box([-0.14, 0.02, 0.07], [0.14, 0.18, 0.09]),
        make_box([-0.14, 0.18, 0.07], [-0.02, 0.48, 0.09]),
        make_box([0.02, 0.18, 0.07], [0.14, 0.48, 0.09]),
        make_sphere(0.20, center=(0.0, 0.70, 0.0), lats=16, lons=24)
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
    builder.meshes.append({"name": "Mesh_Torso_Backpack", "primitives": [
        add_prim(vy, ny, iy, 0), add_prim(va, na, ia, 1), add_prim(vbp, nbp, ibp, 2), add_prim(vm, nm, im, 3), add_prim(vs, ns, is_, 4)
    ]})

    # Extremidades
    v_ua, n_ua, i_ua = combine_prims([make_box([-0.045, -0.26, -0.045], [0.045, 0.0, 0.045]), make_sphere(0.045, (0,0,0), 8, 12)])
    v_lfa, n_lfa, i_lfa = combine_prims([make_box([-0.04, -0.26, -0.04], [0.04, 0.0, 0.04]), make_sphere(0.042, (0, -0.27, 0.01), 8, 12)])
    v_rfa, n_rfa, i_rfa = combine_prims([make_box([-0.04, -0.26, -0.04], [0.04, 0.0, 0.04]), make_sphere(0.045, (0, -0.27, 0.01), 8, 12)])
    v_sw, n_sw, i_sw = make_cylinder_between_points([0.04, -0.10, -0.15], [-0.02, -0.72, 0.42], 0.016, 12)
    v_sm, n_sm, i_sm = combine_prims([
        make_cylinder_between_points([-0.02, -0.72, 0.42], [-0.03, -0.78, 0.48], 0.012, 10),
        make_cylinder_between_points([0.04, -0.10, -0.15], [0.05, -0.04, -0.20], 0.020, 10)
    ])
    v_th, n_th, i_th = combine_prims([make_box([-0.06, -0.38, -0.06], [0.06, 0.0, 0.06]), make_sphere(0.06, (0,0,0), 8, 12)])
    v_calf, n_calf, i_calf = combine_prims([
        make_box([-0.055, -0.38, -0.055], [0.055, 0.0, 0.055]),
        make_sphere(0.055, (0,0,0), 8, 12),
        make_box([-0.055, -0.42, -0.055], [0.055, -0.36, 0.14])
    ])

    builder.meshes.append({"name": "Mesh_Left_UpperArm", "primitives": [add_prim(v_ua, n_ua, i_ua, 0)]})
    builder.meshes.append({"name": "Mesh_Left_Forearm", "primitives": [add_prim(v_lfa, n_lfa, i_lfa, 0)]})
    builder.meshes.append({"name": "Mesh_Right_UpperArm", "primitives": [add_prim(v_ua, n_ua, i_ua, 0)]})
    builder.meshes.append({"name": "Mesh_Right_Forearm_Stick", "primitives": [add_prim(v_rfa, n_rfa, i_rfa, 0), add_prim(v_sw, n_sw, i_sw, 5), add_prim(v_sm, n_sm, i_sm, 6)]})
    builder.meshes.append({"name": "Mesh_Left_Thigh", "primitives": [add_prim(v_th, n_th, i_th, 0)]})
    builder.meshes.append({"name": "Mesh_Left_Calf", "primitives": [add_prim(v_calf, n_calf, i_calf, 0)]})
    builder.meshes.append({"name": "Mesh_Right_Thigh", "primitives": [add_prim(v_th, n_th, i_th, 0)]})
    builder.meshes.append({"name": "Mesh_Right_Calf", "primitives": [add_prim(v_calf, n_calf, i_calf, 0)]})

    # Curvas de animación (1.2 s a 25 fps)
    times = np.linspace(0, 1.2, 29, dtype=np.float32)
    phases = 2.0 * np.pi * times / 1.2
    y_bob = 0.82 + 0.022 * (-np.cos(2 * phases))
    x_sway = 0.015 * np.sin(phases)
    hip_trans = np.column_stack([x_sway, y_bob, np.zeros_like(times)])

    hip_q, l_thigh_q, r_thigh_q, l_calf_q, r_calf_q, l_ua_q, l_fa_q, r_ua_q, r_fa_q = [], [], [], [], [], [], [], [], []
    for p in phases:
        # Caderas
        q = quat_mul(axis_angle_to_quat([0, 1, 0], np.radians(3.5) * np.sin(p)), axis_angle_to_quat([1, 0, 0], np.radians(-7.0)))
        hip_q.append(q / np.linalg.norm(q))
        # Muslos
        ql = axis_angle_to_quat([1, 0, 0], np.radians(-24.0 * np.cos(p)))
        qr = axis_angle_to_quat([1, 0, 0], np.radians(-24.0 * np.cos(p + np.pi)))
        l_thigh_q.append(ql); r_thigh_q.append(qr)
        # Rodillas
        def knee(ph):
            m = ph % (2*np.pi)
            return np.radians(5.0 + 38.0 * np.exp(-((m - 1.35 * np.pi)/0.6)**2))
        l_calf_q.append(axis_angle_to_quat([1, 0, 0], knee(p)))
        r_calf_q.append(axis_angle_to_quat([1, 0, 0], knee(p + np.pi)))
        # Brazos
        l_ua_q.append(axis_angle_to_quat([1, 0, 0], np.radians(20.0 * np.cos(p))))
        l_fa_q.append(axis_angle_to_quat([1, 0, 0], np.radians(22.0 - 12.0 * np.cos(p))))
        r_ua_q.append(axis_angle_to_quat([1, 0, 0], np.radians(-16.0 - 18.0 * np.cos(p))))
        r_fa_q.append(axis_angle_to_quat([1, 0, 0], np.radians(38.0 + 10.0 * np.cos(p))))

    builder.nodes = [
        {"name": "Hiker_Root", "children": [1], "translation": [0.0, 0.0, 0.0]},
        {"name": "Hips_Torso", "mesh": 0, "children": [2, 4, 6, 8], "translation": hip_trans[0].tolist(), "rotation": hip_q[0].tolist()},
        {"name": "Left_UpperArm", "mesh": 1, "children": [3], "translation": [-0.22, 0.44, 0.0], "rotation": l_ua_q[0].tolist()},
        {"name": "Left_Forearm", "mesh": 2, "translation": [0.0, -0.26, 0.0], "rotation": l_fa_q[0].tolist()},
        {"name": "Right_UpperArm", "mesh": 3, "children": [5], "translation": [0.22, 0.44, 0.0], "rotation": r_ua_q[0].tolist()},
        {"name": "Right_Forearm", "mesh": 4, "translation": [0.0, -0.26, 0.0], "rotation": r_fa_q[0].tolist()},
        {"name": "Left_Thigh", "mesh": 5, "children": [7], "translation": [-0.09, 0.0, 0.0], "rotation": l_thigh_q[0].tolist()},
        {"name": "Left_Calf", "mesh": 6, "translation": [0.0, -0.38, 0.0], "rotation": l_calf_q[0].tolist()},
        {"name": "Right_Thigh", "mesh": 7, "children": [9], "translation": [0.09, 0.0, 0.0], "rotation": r_thigh_q[0].tolist()},
        {"name": "Right_Calf", "mesh": 8, "translation": [0.0, -0.38, 0.0], "rotation": r_calf_q[0].tolist()}
    ]

    time_acc = builder.add_time_accessor(times)
    channels, samplers = [], []
    def add_ch(node_id, path, data, is_vec3=False):
        acc = builder.add_vec3_keyframes(data) if is_vec3 else builder.add_quat_keyframes(np.array(data, dtype=np.float32))
        s_idx = len(samplers)
        samplers.append({"input": time_acc, "interpolation": "LINEAR", "output": acc})
        channels.append({"sampler": s_idx, "target": {"node": node_id, "path": path}})

    add_ch(1, "translation", hip_trans, is_vec3=True)
    add_ch(1, "rotation", hip_q)
    add_ch(2, "rotation", l_ua_q)
    add_ch(3, "rotation", l_fa_q)
    add_ch(4, "rotation", r_ua_q)
    add_ch(5, "rotation", r_fa_q)
    add_ch(6, "rotation", l_thigh_q)
    add_ch(7, "rotation", l_calf_q)
    add_ch(8, "rotation", r_thigh_q)
    add_ch(9, "rotation", r_calf_q)

    builder.animations.append({"name": "WalkCycle", "channels": channels, "samplers": samplers})
    builder.build_glb("pegman_hiker_walk.glb")

if __name__ == "__main__":
    generate_hiker()
