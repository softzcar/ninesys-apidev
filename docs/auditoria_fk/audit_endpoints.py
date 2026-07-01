import re, os, glob

ROUTES = "app/routes"
FK_CHILD = set("""abonos aprobacion_clientes caja caja_fondos catalogo_ciudades catalogo_estados
catalogo_impresoras catalogo_insumos_productos catalogo_tintas check_tareas crm_campanas_envios
crm_notas crm_oportunidades crm_oportunidades_vendedores crm_soporte customers disenos
disenos_ajustes_y_personalizaciones empleados_lotes_fabricacion empleados_lotes_fabricacion_items
gastos_registros impresoras_colores inventario inventario_movimientos inventario_movimientos_historial
inventario_remanentes lotes lotes_detalles lotes_detalles_empleados_asignados
lotes_detalles_empleados_asignados_pausas lotes_fisicos lotes_historico_solicitadas metodos_de_pago
ordenes ordenes_auditoria ordenes_borrador_empleado ordenes_fila_orden ordenes_fila_reposiciones
ordenes_observaciones ordenes_productos ordenes_vinculadas pagos pagos_abonos pagos_descuentos
pagos_salarios piezas_cortadas presupuestos presupuestos_productos product_insumos_asignados
products_attributes_values products_comisiones products_prices products_sizes_eficiencia
products_tiempos_de_produccion rendimiento reposiciones reposiciones_departamentos_excluidos
revisiones tintas tintas_recargas""".split())
FK_PARENT = set("""caja_cierres catalogo_ciudades catalogo_colores_tintas catalogo_estados
catalogo_impresoras catalogo_insumos_productos catalogo_paises catalogo_telas catalogo_tintas
categories crm_campanas crm_oportunidades customers departamentos disenos empleados_lotes_fabricacion
gastos inventario inventario_movimientos lotes_detalles lotes_detalles_empleados_asignados
lotes_fisicos metodos_de_pago ordenes ordenes_productos pagos presupuestos products
products_attributes reposiciones sizes""".split())
FK_ANY = FK_CHILD | FK_PARENT

endpoint_re = re.compile(r"""\$app->(get|post|put|delete|patch|options)\(\s*['"]([^'"]+)['"]""", re.I)
ins_re = re.compile(r"INSERT\s+INTO\s+`?([a-zA-Z_]+)`?", re.I)
upd_re = re.compile(r"UPDATE\s+`?([a-zA-Z_]+)`?\s+SET", re.I)
del_re = re.compile(r"DELETE\s+FROM\s+`?([a-zA-Z_]+)`?", re.I)

rows=[]
for f in sorted(glob.glob(os.path.join(ROUTES,"*.php"))):
    if f.endswith(".bak"): continue
    lines=open(f,encoding="utf-8",errors="replace").read().split("\n")
    eps=[(i,m.group(1).upper(),m.group(2)) for i,l in enumerate(lines) for m in [endpoint_re.search(l)] if m]
    for idx,(ln,method,path) in enumerate(eps):
        end = eps[idx+1][0] if idx+1<len(eps) else len(lines)
        block="\n".join(lines[ln:end])
        ins={t for t in ins_re.findall(block)}
        upd={t for t in upd_re.findall(block)}
        dele={t for t in del_re.findall(block)}
        writes=ins|upd|dele
        if not writes: continue
        fk_ins_upd = (ins|upd) & FK_CHILD          # 1452 risk
        fk_del = dele & FK_PARENT                    # 1451/cascade risk
        if not (fk_ins_upd or fk_del): continue
        has_tx = "beginTransaction" in block
        has_rb = re.search(r"rollBack|rollback", block) is not None
        nwrites = len(ins_re.findall(block))+len(upd_re.findall(block))+len(del_re.findall(block))
        rows.append(dict(file=os.path.basename(f), line=ln+1, method=method, path=path,
                         nwrites=nwrites, tx=has_tx, rb=has_rb,
                         fk_iu=sorted(fk_ins_upd), fk_del=sorted(fk_del)))

# verdict
def verdict(r):
    multi = r["nwrites"]>1
    if r["tx"] and r["rb"]: return "A-atomico"
    if not multi: return "C-cosmetico(1 escritura)"
    return "B-BRECHA(multi sin tx/rb)"

from collections import Counter
c=Counter(verdict(r) for r in rows)
print("TOTAL endpoints de ESCRITURA que tocan tablas FK:", len(rows))
print("Resumen:", dict(c))
print("Por archivo:", dict(Counter(r["file"] for r in rows)))
print()
print("=== BUCKET B — BRECHA DE ATOMICIDAD (multi-escritura sin transacción) ===")
for r in sorted(rows,key=lambda x:(x["file"],x["line"])):
    if verdict(r).startswith("B"):
        tgt=",".join(r["fk_iu"]+r["fk_del"])
        print(f'{r["file"]}:{r["line"]:<5} {r["method"]:6} {r["path"]:<48} w={r["nwrites"]} [{tgt}]')

print("\n\n########## REFINADO ##########")
writers=[r for r in rows if r["method"] in ("POST","PUT","DELETE","PATCH")]
gets=[r for r in rows if r["method"] in ("GET","OPTIONS")]
def distinct_fk(r): return sorted(set(r["fk_iu"])|set(r["fk_del"]))
# B real = escritor (no GET), multi-tabla FK distinta, sin tx+rb
Breal=[r for r in writers if len(distinct_fk(r))>=2 and not (r["tx"] and r["rb"])]
Bsingle=[r for r in writers if len(distinct_fk(r))==1 and r["nwrites"]>1 and not (r["tx"] and r["rb"])]
A=[r for r in writers if r["tx"] and r["rb"]]
print(f"Escritores POST/PUT/DELETE sobre FK: {len(writers)}")
print(f"  A (transaccion+rollback): {len(A)}")
print(f"  B-REAL (>=2 tablas FK distintas, sin tx): {len(Breal)}")
print(f"  B-single (1 tabla, multi-sentencia, sin tx): {len(Bsingle)}")
print(f"  GET/OPTIONS con aparente escritura (revisar boundary/REST): {len(gets)}")
print()
print("=== B-REAL: multi-tabla FK sin transaccion (PRIORIDAD, ordenado por nº tablas) ===")
for r in sorted(Breal,key=lambda x:(-len(distinct_fk(x)),x["file"])):
    print(f'{len(distinct_fk(r))}t {r["file"]}:{r["line"]:<5} {r["method"]:5} {r["path"]:<46} -> {",".join(distinct_fk(r))}')
print()
print("=== A: ya atomicos (transaccion+rollback) ===")
for r in sorted(A,key=lambda x:(x["file"],x["line"])):
    print(f'   {r["file"]}:{r["line"]:<5} {r["path"]}')
print()
print("=== GET/OPTIONS con aparente escritura (probable falso positivo) ===")
for r in gets:
    print(f'   {r["file"]}:{r["line"]:<5} {r["method"]} {r["path"]}')
